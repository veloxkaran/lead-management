<?php

namespace App\Http\Controllers;

use App\Exports\GenericTableExport;
use App\Imports\RawDataImport;
use App\Models\RawData;
use App\Services\RawDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\Failure;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RawDataBulkUploadController extends Controller
{
    private const COLUMNS = ['Contact Person', 'Company Name', 'Phone', 'Email', 'Source', 'Notes'];

    // Safety cap on the paste-grid endpoint: unlike the file upload (bounded by what
    // Excel can hold in a sheet), this accepts a raw JSON array in the request body.
    private const MAX_PASTE_ROWS = 2000;

    public function __construct(protected RawDataService $rawDataService)
    {
    }

    public function create(): View
    {
        $this->authorize('create', RawData::class);

        return view('raw-data.bulk-upload');
    }

    public function template(): BinaryFileResponse
    {
        $this->authorize('create', RawData::class);

        return Excel::download(
            new GenericTableExport(self::COLUMNS, [
                ['Jane Doe', 'Acme Corp', '9800000000', 'jane@example.test', 'Referral', 'Met at trade show'],
            ]),
            'raw-data-bulk-upload-template.xlsx'
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', RawData::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $import = new RawDataImport($this->rawDataService, $request->user());

        Excel::import($import, $request->file('file'));

        return redirect()->route('raw-data.bulk-upload.create')
            ->with('success', "{$import->importedCount()} raw data entries imported, {$import->updatedCount()} existing entries filled in with new details.")
            ->with('importFailures', $import->failures());
    }

    /**
     * Counterpart to store() for the paste-grid UI: same row rules
     * (RawDataImport::rowRules()) and the same dedup/fill-in behavior
     * (RawDataService::importRow()), just sourced from a JSON blob the
     * browser built from pasted spreadsheet cells instead of an uploaded
     * file. Failures are reported as Maatwebsite\Excel Failure objects so
     * the view's existing "skipped rows" panel renders both the same way.
     */
    public function storePasted(Request $request): RedirectResponse
    {
        $this->authorize('create', RawData::class);

        $request->validate([
            'rows' => ['required', 'string'],
        ]);

        $rows = json_decode($request->input('rows'), true);

        if (! is_array($rows)) {
            return redirect()->route('raw-data.bulk-upload.create')
                ->with('error', 'Could not read the pasted data. Please try again.');
        }

        if (count($rows) > self::MAX_PASTE_ROWS) {
            return redirect()->route('raw-data.bulk-upload.create')
                ->with('error', 'Too many rows pasted at once (max '.self::MAX_PASTE_ROWS.'). Split the data into smaller batches.');
        }

        $imported = 0;
        $updated = 0;
        $failures = [];

        foreach (array_values($rows) as $index => $row) {
            $row = is_array($row) ? $row : [];

            $attributes = [
                'contact_person' => trim((string) ($row['contact_person'] ?? '')),
                'company_name' => trim((string) ($row['company_name'] ?? '')) ?: null,
                'phone' => trim((string) ($row['phone'] ?? '')),
                'email' => trim((string) ($row['email'] ?? '')) ?: null,
                'source' => trim((string) ($row['source'] ?? '')) ?: null,
                'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
            ];

            if ($attributes['contact_person'] === '' && $attributes['phone'] === ''
                && blank($attributes['company_name']) && blank($attributes['email'])
                && blank($attributes['source']) && blank($attributes['notes'])) {
                continue;
            }

            $validator = Validator::make($attributes, RawDataImport::rowRules());

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $attribute => $messages) {
                    $failures[] = new Failure($index + 1, $attribute, $messages, $attributes);
                }

                continue;
            }

            $result = $this->rawDataService->importRow($validator->validated(), $request->user());

            match ($result) {
                'created' => $imported++,
                'updated' => $updated++,
                default => null,
            };
        }

        return redirect()->route('raw-data.bulk-upload.create')
            ->with('success', "{$imported} raw data entries imported, {$updated} existing entries filled in with new details.")
            ->with('importFailures', $failures);
    }
}
