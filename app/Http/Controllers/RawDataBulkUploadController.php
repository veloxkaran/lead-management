<?php

namespace App\Http\Controllers;

use App\Exports\GenericTableExport;
use App\Imports\RawDataImport;
use App\Models\RawData;
use App\Services\RawDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RawDataBulkUploadController extends Controller
{
    private const COLUMNS = ['Contact Person', 'Phone', 'Email', 'Source'];

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
                ['Jane Doe', '9800000000', 'jane@example.test', 'Referral'],
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
            ->with('success', "{$import->importedCount()} raw data entries imported successfully.")
            ->with('importFailures', $import->failures());
    }
}
