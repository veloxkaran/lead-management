<?php

namespace App\Http\Controllers;

use App\Exports\GenericTableExport;
use App\Imports\LeadsImport;
use App\Models\Lead;
use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeadBulkUploadController extends Controller
{
    /**
     * Basic-details-only columns, shared by the downloadable template and
     * the import's validation/mapping — one source of truth so the two
     * can never drift apart.
     */
    private const COLUMNS = ['Company Name', 'Contact Person', 'Email', 'Phone', 'Industry', 'Source'];

    public function __construct(protected LeadService $leadService)
    {
    }

    public function create(): View
    {
        $this->authorize('create', Lead::class);

        return view('leads.bulk-upload');
    }

    public function template(): BinaryFileResponse
    {
        $this->authorize('create', Lead::class);

        return Excel::download(
            new GenericTableExport(self::COLUMNS, [
                ['Acme Corp', 'Jane Doe', 'jane@acme.test', '9800000000', 'Retail', 'Referral'],
            ]),
            'lead-bulk-upload-template.xlsx'
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $import = new LeadsImport($this->leadService, $request->user());

        Excel::import($import, $request->file('file'));

        return redirect()->route('leads.bulk-upload.create')
            ->with('success', "{$import->importedCount()} lead(s) imported successfully.")
            ->with('importFailures', $import->failures());
    }
}
