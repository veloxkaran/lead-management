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

    public function rules(): array
    {
        return [
            'contact_person' => ['required', 'max:255'],
            'phone' => ['required', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['nullable', 'max:20'],
        ];
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $contactPerson = (string) $row['contact_person'];
            $phone = (string) $row['phone'];
            $attributes = [
                'email' => $row['email'] ?? null,
                'source' => $row['source'] ?? null,
            ];

            $existing = $this->rawDataService->findExistingForImportRow($contactPerson, $phone);

            if ($existing) {
                if ($this->rawDataService->fillMissingDetails($existing, $attributes)) {
                    $this->updatedCount++;
                }

                continue;
            }

            $this->rawDataService->create([
                'contact_person' => $contactPerson,
                'phone' => $phone,
                ...$attributes,
            ], $this->creator);

            $this->importedCount++;
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
