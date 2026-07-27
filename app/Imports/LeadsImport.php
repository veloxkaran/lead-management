<?php

namespace App\Imports;

use App\Models\User;
use App\Rules\NotDuplicateLeadName;
use App\Services\LeadService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Bulk lead import — only the "basic details" fields are accepted (company
 * name, contact person, email, phone, industry, source); everything else
 * (assignment, status, financials, etc.) is left for the normal edit
 * screen afterward. Rows are created through LeadService::create() rather
 * than saved directly, so a bulk-imported lead gets exactly the same
 * default status + status-history entry as one created by hand — no
 * parallel/divergent creation path. Invalid rows are skipped (not the
 * whole file) and collected via SkipsFailures for the results page.
 */
class LeadsImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure, SkipsEmptyRows
{
    use SkipsFailures;

    private int $importedCount = 0;

    public function __construct(private LeadService $leadService, private User $creator)
    {
    }

    /**
     * No 'string' type rule on these: PhpSpreadsheet auto-types purely
     * numeric-looking cells (a phone number like "9800000000" most
     * commonly) as int/float rather than string, which the 'string' rule
     * would then wrongly reject. max: still measures character length
     * correctly either way (Laravel only switches to a numeric comparison
     * when a 'numeric'/'integer' rule is also present).
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'max:255', new NotDuplicateLeadName],
            'contact_person' => ['required', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'max:30'],
            'industry' => ['nullable', 'max:255'],
            'source' => ['nullable', 'max:255'],
        ];
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $this->leadService->create([
                'company_name' => $row['company_name'],
                'contact_person' => $row['contact_person'],
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
                'industry' => $row['industry'] ?? null,
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
