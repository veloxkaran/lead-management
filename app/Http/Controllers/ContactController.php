<?php

namespace App\Http\Controllers;

use App\Exports\GenericTableExport;
use App\Http\Requests\Contact\ContactRequest;
use App\Imports\RowsImport;
use App\Models\Campaign;
use App\Models\Contact;
use App\Services\ContactImporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Contacts menu: the shared address book. Adding and editing happen in a
 * modal on the list page; bulk import takes rows pasted from a
 * spreadsheet or an uploaded file. Selected contacts can be sent an email
 * or SMS campaign straight from the list.
 */
class ContactController extends Controller
{
    private const FILTERS = ['email', 'phone', 'mine'];

    private const SORTS = [
        'newest' => ['created_at', 'desc'],
        'company' => ['company_name', 'asc'],
        'name' => ['name', 'asc'],
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Contact::class);

        $filters = [
            'search' => trim((string) $request->query('search')),
            'filter' => in_array($request->query('filter'), self::FILTERS, true) ? $request->query('filter') : null,
            'sort' => array_key_exists((string) $request->query('sort'), self::SORTS) ? $request->query('sort') : 'newest',
        ];

        [$column, $direction] = self::SORTS[$filters['sort']];

        $contacts = $this->filtered($request, $filters)
            ->with('creator:id,name')
            // Blank names/companies sort last, not first.
            ->when($direction === 'asc', fn ($q) => $q->orderByRaw("{$column} IS NULL")->orderByRaw("LOWER({$column})"), fn ($q) => $q->orderBy($column, $direction))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $user = $request->user();

        return view('contacts.index', [
            'contacts' => $contacts,
            'filters' => $filters,
            'counts' => [
                'all' => Contact::count(),
                'email' => Contact::whereNotNull('email')->count(),
                'phone' => Contact::whereNotNull('phone')->count(),
                'mine' => Contact::where('created_by', $user->id)->count(),
            ],
            'canCampaign' => $user->can('create', Campaign::class) && $user->hasPermission('campaigns', 'create'),
        ]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $contact = Contact::create([...$request->only(Contact::FIELDS), 'created_by' => $request->user()->id]);

        $redirect = redirect()->route('contacts.index', $request->boolean('add_another') ? [] : array_filter($request->only('search', 'filter', 'sort')))
            ->with('success', "Contact \"{$contact->displayName()}\" added.");

        // "Save & add another" reopens an empty form.
        return $request->boolean('add_another') ? $redirect->with('contactFormOpen', true) : $redirect;
    }

    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->only(Contact::FIELDS));

        return back()->with('success', "Contact \"{$contact->displayName()}\" updated.");
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return back()->with('success', "Contact \"{$contact->displayName()}\" deleted.");
    }

    /**
     * Deletes the selected contacts the user is allowed to delete; any
     * others (added by someone else) are left and counted.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:1000'], 'ids.*' => ['integer']])['ids'];

        $contacts = Contact::whereIn('id', $ids)->get();
        $deletable = $contacts->filter(fn (Contact $contact) => $request->user()->can('delete', $contact));

        Contact::whereIn('id', $deletable->modelKeys())->delete();

        $kept = $contacts->count() - $deletable->count();

        return back()->with('success', $deletable->count().' contact(s) deleted.'.($kept ? " {$kept} added by someone else were left — only they, a Manager or a Super Admin can delete those." : ''));
    }

    public function importForm(): View
    {
        $this->authorize('create', Contact::class);

        return view('contacts.import', ['maxRows' => ContactImporter::MAX_ROWS]);
    }

    public function import(Request $request, ContactImporter $importer): RedirectResponse
    {
        $this->authorize('create', Contact::class);

        $data = $request->validate([
            'rows' => ['nullable', 'required_without:file', 'string', 'max:2000000'],
            'file' => ['nullable', 'required_without:rows', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ], [
            'rows.required_without' => 'Paste some rows, or choose a file to upload.',
            'file.required_without' => 'Paste some rows, or choose a file to upload.',
        ]);

        $rows = $request->hasFile('file')
            ? (Excel::toArray(new RowsImport, $request->file('file'))[0] ?? [])
            : ContactImporter::parsePaste($data['rows']);

        $result = $importer->import($rows, $request->user());

        return redirect()->route('contacts.import')
            ->with($result['imported'] ? 'success' : 'error', $result['imported']
                ? number_format($result['imported']).' contact(s) imported.'
                : 'No contacts were imported — see the skipped rows below.')
            ->with('importResult', [...$result, 'skipped' => array_slice($result['skipped'], 0, 500), 'skipped_count' => count($result['skipped'])]);
    }

    public function template(): BinaryFileResponse
    {
        $this->authorize('create', Contact::class);

        return Excel::download(new GenericTableExport(['Company Name', 'Name', 'Email', 'Phone'], [
            ['Acme Pvt. Ltd.', 'Ram Sharma', 'ram@acme.com.np', '9800000000'],
            ['', 'Sita Thapa', '', '9811111111'],
        ]), 'contacts-template.xlsx');
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Contact::class);

        $filters = [
            'search' => trim((string) $request->query('search')),
            'filter' => in_array($request->query('filter'), self::FILTERS, true) ? $request->query('filter') : null,
        ];

        $rows = $this->filtered($request, $filters)->with('creator:id,name')->orderBy('id')->get()
            ->map(fn (Contact $c) => [$c->company_name, $c->name, $c->email, $c->phone, $c->creator?->name, $c->created_at?->format('Y-m-d H:i')])
            ->all();

        return Excel::download(
            new GenericTableExport(['Company Name', 'Name', 'Email', 'Phone', 'Added By', 'Added On'], $rows),
            'contacts-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    /**
     * @param  array{search: string, filter: ?string}  $filters
     */
    private function filtered(Request $request, array $filters): Builder
    {
        return Contact::query()
            ->search($filters['search'])
            ->when($filters['filter'] === 'email', fn ($q) => $q->whereNotNull('email'))
            ->when($filters['filter'] === 'phone', fn ($q) => $q->whereNotNull('phone'))
            ->when($filters['filter'] === 'mine', fn ($q) => $q->where('created_by', $request->user()->id));
    }
}
