<?php

namespace App\Imports;

use App\Models\User;
use App\Services\RawDataService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * A row matching an existing entry (by phone, or failing that contact
 * person) is never rejected as a duplicate here — instead its currently-null
 * email/source get filled in from the row via
 * RawDataService::fillMissingDetails(), which never overwrites a value
 * that's already set. Only genuinely new contacts go through
 * RawDataService::create(), so a bulk-imported entry still gets the same
 * created_by/default-status handling as one created by hand.
 */
class RawDataImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure, SkipsEmptyRows
{
    use SkipsFailures;

    private int $importedCount = 0;

    private int $updatedCount = 0;

    public function __construct(private RawDataService $rawDataService, private User $creator)
    {
    }

    /**
     * Shared with the paste-grid importer (RawDataBulkUploadController::storePasted())
     * so both entry points reject/accept rows identically.
     */
    public static function rowRules(): array
    {
        return [
            'contact_person' => ['required', 'max:255'],
            'company_name' => ['nullable', 'max:255'],
            'number_of_employees' => ['nullable', 'integer', 'min:0'],
            'phone' => ['required', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['nullable', 'max:20'],
            'notes' => ['nullable', 'max:2000'],
        ];
    }

    public function rules(): array
    {
        return self::rowRules();
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $result = $this->rawDataService->importRow([
                'contact_person' => (string) $row['contact_person'],
                'company_name' => $row['company_name'] ?? null,
                'number_of_employees' => $row['number_of_employees'] ?? null,
                'phone' => (string) $row['phone'],
                'email' => $row['email'] ?? null,
                'source' => $row['source'] ?? null,
                'notes' => $row['notes'] ?? null,
            ], $this->creator);

            match ($result) {
                'created' => $this->importedCount++,
                'updated' => $this->updatedCount++,
                default => null,
            };
        }
    }

    public function importedCount(): int
    {
        return $this->importedCount;
    }

    public function updatedCount(): int
    {
        return $this->updatedCount;
    }
}
