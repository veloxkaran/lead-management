<?php

namespace App\Imports;

use App\Models\User;
use App\Rules\NotDuplicateRawContact;
use App\Services\RawDataService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Rows are created through RawDataService::create() rather than saved
 * directly, so a bulk-imported entry gets the same created_by/default-status
 * handling as one created by hand. Duplicate-check rules are re-instantiated
 * per row (not shared) so an earlier row in the same file can't be matched
 * against a later one within the same NotDuplicateRawContact instance.
 */
class RawDataImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure, SkipsEmptyRows
{
    use SkipsFailures;

    private int $importedCount = 0;

    public function __construct(private RawDataService $rawDataService, private User $creator)
    {
    }

    public function rules(): array
    {
        return [
            'contact_person' => ['required', 'max:255', new NotDuplicateRawContact('contact_person')],
            'phone' => ['required', 'max:30', new NotDuplicateRawContact('phone')],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['nullable', 'max:20'],
        ];
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $this->rawDataService->create([
                'contact_person' => $row['contact_person'],
                'phone' => $row['phone'],
                'email' => $row['email'] ?? null,
                'source' => $row['source'] ?? null,
            ], $this->creator);

            $this->importedCount++;
        }
    }

    public function importedCount(): int
    {
        return $this->importedCount;
    }
}
