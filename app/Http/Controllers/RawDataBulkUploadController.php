<?php

namespace App\Http\Controllers;

use App\Exports\GenericTableExport;
use App\Imports\RawDataImport;
use App\Models\RawData;
use App\Models\RawDataImportBatch;
use App\Models\RawDataImportRejection;
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
    private const COLUMNS = ['Contact Person', 'Company Name', 'Number of Employees', 'Phone', 'Email', 'Source', 'Notes'];

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
                ['Jane Doe', 'Acme Corp', '50', '9800000000', 'jane@example.test', 'Referral', 'Met at trade show'],
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

        $batch = $this->rawDataService->recordImportBatch(
            $import->failures(),
            $import->importedCount(),
            $import->updatedCount(),
            $import->unchangedCount(),
            $request->user(),
            'file',
            $request->file('file')->getClientOriginalName(),
        );

        return redirect()->route('raw-data.bulk-upload.batches.show', $batch)
            ->with('success', "{$batch->imported_count} raw data entries imported, {$batch->updated_count} existing entries filled in with new details, {$batch->rejected_count} row(s) rejected.");
    }

    /**
     * Counterpart to store() for the paste-grid UI: same row rules
     * (RawDataImport::rowRules()) and the same dedup/fill-in behavior
     * (RawDataService::importRow()), just sourced from a JSON blob the
     * browser built from pasted spreadsheet cells instead of an uploaded
     * file. Failures are built as the same Maatwebsite\Excel Failure objects
     * the file importer collects, so recordImportBatch() consolidates and
     * persists both entry points identically.
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
        $unchanged = 0;
        $failures = [];

        foreach (array_values($rows) as $index => $row) {
            $row = is_array($row) ? $row : [];

            $attributes = [
                'contact_person' => trim((string) ($row['contact_person'] ?? '')),
                'company_name' => trim((string) ($row['company_name'] ?? '')) ?: null,
                'number_of_employees' => trim((string) ($row['number_of_employees'] ?? '')) ?: null,
                'phone' => trim((string) ($row['phone'] ?? '')),
                'email' => trim((string) ($row['email'] ?? '')) ?: null,
                'source' => trim((string) ($row['source'] ?? '')) ?: null,
                'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
            ];

            if ($attributes['contact_person'] === '' && $attributes['phone'] === ''
                && blank($attributes['company_name']) && blank($attributes['number_of_employees'])
                && blank($attributes['email']) && blank($attributes['source']) && blank($attributes['notes'])) {
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
                'unchanged' => $unchanged++,
                default => null,
            };
        }

        $batch = $this->rawDataService->recordImportBatch(
            $failures,
            $imported,
            $updated,
            $unchanged,
            $request->user(),
            'paste',
        );

        return redirect()->route('raw-data.bulk-upload.batches.show', $batch)
            ->with('success', "{$batch->imported_count} raw data entries imported, {$batch->updated_count} existing entries filled in with new details, {$batch->rejected_count} row(s) rejected.");
    }

    public function showBatch(RawDataImportBatch $batch): View
    {
        $this->authorize('create', RawData::class);

        $rejections = $batch->rejections()->orderBy('row_number')->paginate(25);

        return view('raw-data.bulk-upload-results', compact('batch', 'rejections'));
    }

    public function downloadBatchRejections(RawDataImportBatch $batch): BinaryFileResponse
    {
        $this->authorize('create', RawData::class);

        $rows = $batch->rejections()->orderBy('row_number')->get()
            ->map(function (RawDataImportRejection $rejection) {
                $raw = $rejection->raw_data;

                return [
                    $raw['contact_person'] ?? '',
                    $raw['company_name'] ?? '',
                    $raw['number_of_employees'] ?? '',
                    $raw['phone'] ?? '',
                    $raw['email'] ?? '',
                    $raw['source'] ?? '',
                    $raw['notes'] ?? '',
                    collect($rejection->errors)->flatten()->implode('; '),
                ];
            })
            ->all();

        return Excel::download(
            new GenericTableExport([...self::COLUMNS, 'Import Error'], $rows),
            "raw-data-import-{$batch->id}-rejections.xlsx"
        );
    }
}
