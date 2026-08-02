<?php

namespace App\Services;

use App\Enums\RawDataAssignmentAction;
use App\Enums\RawDataStatus;
use App\Models\Lead;
use App\Models\RawData;
use App\Models\RawDataComment;
use App\Models\RawDataImportBatch;
use App\Models\User;
use App\Repositories\RawDataRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Validators\Failure;

class RawDataService
{
    public function __construct(
        protected RawDataRepository $rawData,
        protected LeadService $leadService,
    ) {
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->rawData->filter($filters, $perPage);
    }

    public function create(array $attributes, User $creator): RawData
    {
        $attributes['created_by'] = $creator->id;

        return $this->rawData->create($attributes);
    }

    public function markNotValid(RawData $rawData): RawData
    {
        $this->guardActionable($rawData);

        $rawData->update(['status' => RawDataStatus::NotValid]);

        return $rawData;
    }

    public function markHold(RawData $rawData): RawData
    {
        $this->guardActionable($rawData);

        $rawData->update(['status' => RawDataStatus::Hold]);

        return $rawData;
    }

    /**
     * Creates the Lead through LeadService::create() (not a direct save),
     * so a raw-data-originated lead gets exactly the same default status +
     * status-history entry as one entered by hand — no parallel creation
     * path. Both writes happen in one transaction so a failure creating
     * the Lead can't leave the Raw Data entry stranded in a half-converted
     * state.
     */
    public function convertToLead(RawData $rawData, array $leadAttributes, User $actor): Lead
    {
        $this->guardActionable($rawData);

        return DB::transaction(function () use ($rawData, $leadAttributes, $actor) {
            $lead = $this->leadService->create($leadAttributes, $actor);

            $rawData->update([
                'status' => RawDataStatus::ConvertedToLead,
                'converted_lead_id' => $lead->id,
            ]);

            return $lead;
        });
    }

    /**
     * Bulk-upload-only lookup: matches by phone first (the more reliable
     * identifier), falling back to contact person, so a re-uploaded row for
     * someone already on file gets reconciled with fillMissingDetails()
     * instead of being rejected as a duplicate.
     *
     * A blank value is never used as a match key: 'lower(phone) = ""' would
     * otherwise match *any* existing row that also has a blank phone,
     * silently collapsing every subsequent blank-phone import row into the
     * first one ever created (and discarding its data) instead of treating
     * each as a distinct new contact.
     */
    public function findExistingForImportRow(string $contactPerson, string $phone): ?RawData
    {
        $phone = trim($phone);
        $contactPerson = trim($contactPerson);

        if ($phone !== '') {
            $existing = $this->rawData->query()->whereRaw('lower(phone) = ?', [mb_strtolower($phone)])->first();

            if ($existing) {
                return $existing;
            }
        }

        if ($contactPerson !== '') {
            return $this->rawData->query()->whereRaw('lower(contact_person) = ?', [mb_strtolower($contactPerson)])->first();
        }

        return null;
    }

    /**
     * Fills in only the fields that are currently null on the existing
     * entry — never overwrites a value that's already set. Returns whether
     * anything actually changed, so the importer can report an accurate
     * "updated" count.
     */
    public function fillMissingDetails(RawData $rawData, array $attributes): bool
    {
        $updates = [];

        foreach (['company_name', 'number_of_employees', 'email', 'source', 'notes'] as $field) {
            if (blank($rawData->{$field}) && filled($attributes[$field] ?? null)) {
                $updates[$field] = $attributes[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $rawData->update($updates);

        return true;
    }

    /**
     * Shared per-row bulk-import path for both the file importer
     * (RawDataImport) and the paste-grid importer: a row matching an
     * existing entry fills in its missing details rather than creating a
     * duplicate, exactly as findExistingForImportRow()/fillMissingDetails()
     * intend. Returns 'created', 'updated', or 'unchanged' so each caller
     * can tally its own counts.
     */
    public function importRow(array $attributes, User $creator): string
    {
        $contactPerson = trim((string) $attributes['contact_person']);
        $phone = trim((string) $attributes['phone']);
        $rest = [
            'company_name' => $attributes['company_name'] ?? null,
            'number_of_employees' => $attributes['number_of_employees'] ?? null,
            'email' => $attributes['email'] ?? null,
            'source' => $attributes['source'] ?? null,
            'notes' => $attributes['notes'] ?? null,
        ];

        $existing = $this->findExistingForImportRow($contactPerson, $phone);

        if ($existing) {
            return $this->fillMissingDetails($existing, $rest) ? 'updated' : 'unchanged';
        }

        $this->create([
            'contact_person' => $contactPerson,
            'phone' => $phone,
            ...$rest,
        ], $creator);

        return 'created';
    }

    /**
     * Consolidates the per-attribute Maatwebsite\Excel\Validators\Failure
     * objects for an import run (a row with two invalid fields produces two
     * Failure objects) into one rejection log entry per row, and persists
     * the whole run as a durable batch — shared by both the file importer
     * (RawDataImport) and the paste-grid endpoint so their summary/rejection
     * behavior is identical.
     *
     * @param  iterable<Failure>  $failures
     */
    public function recordImportBatch(
        iterable $failures,
        int $importedCount,
        int $updatedCount,
        int $unchangedCount,
        User $user,
        string $source,
        ?string $originalFilename = null,
    ): RawDataImportBatch {
        $rejectedRows = [];

        foreach ($failures as $failure) {
            $row = $failure->row();
            $rejectedRows[$row]['row_number'] = $row;
            $rejectedRows[$row]['raw_data'] = $failure->values();
            $rejectedRows[$row]['errors'][$failure->attribute()] = $failure->errors();
        }

        return DB::transaction(function () use ($rejectedRows, $importedCount, $updatedCount, $unchangedCount, $user, $source, $originalFilename) {
            $batch = RawDataImportBatch::create([
                'user_id' => $user->id,
                'source' => $source,
                'original_filename' => $originalFilename,
                'total_rows' => $importedCount + $updatedCount + $unchangedCount + count($rejectedRows),
                'imported_count' => $importedCount,
                'updated_count' => $updatedCount,
                'unchanged_count' => $unchangedCount,
                'rejected_count' => count($rejectedRows),
            ]);

            foreach ($rejectedRows as $row) {
                $batch->rejections()->create($row);
            }

            return $batch;
        });
    }

    /**
     * assigned_by/assigned_at always move together with assigned_to rather
     * than being independently settable — they log who performed *this*
     * assignment and when, so clearing assigned_to (unassigning) clears
     * them too instead of leaving a stale "assigned by/at" behind.
     *
     * Every actual transition also appends to assignmentLogs(): a change
     * away from a previous assignee logs an Unassigned entry for them, and
     * a change onto a new assignee logs an Assigned entry — a direct
     * reassignment (A to B) logs both, so both event types are always
     * individually visible in the history rather than only the latest
     * assigned_to value. Resubmitting the same assignee is a no-op (no
     * update, no log entry), so it can't reset assigned_at/the countdown.
     */
    public function assign(RawData $rawData, ?int $assignedTo, User $actor): RawData
    {
        $this->guardActionable($rawData);

        $previousAssignedTo = $rawData->assigned_to;

        if ($assignedTo === $previousAssignedTo) {
            return $rawData;
        }

        $rawData->update([
            'assigned_to' => $assignedTo,
            'assigned_by' => $assignedTo ? $actor->id : null,
            'assigned_at' => $assignedTo ? now() : null,
        ]);

        if ($previousAssignedTo !== null) {
            $rawData->assignmentLogs()->create([
                'action' => RawDataAssignmentAction::Unassigned,
                'user_id' => $previousAssignedTo,
                'performed_by' => $actor->id,
            ]);
        }

        if ($assignedTo !== null) {
            $rawData->assignmentLogs()->create([
                'action' => RawDataAssignmentAction::Assigned,
                'user_id' => $assignedTo,
                'performed_by' => $actor->id,
            ]);
        }

        return $rawData;
    }

    public function addComment(RawData $rawData, array $attributes, User $author): RawDataComment
    {
        return $rawData->comments()->create([
            ...$attributes,
            'author_id' => $author->id,
        ]);
    }

    public function delete(RawData $rawData): void
    {
        $this->rawData->delete($rawData);
    }

    private function guardActionable(RawData $rawData): void
    {
        if (! $rawData->isActionable()) {
            throw ValidationException::withMessages([
                'status' => "This entry is already {$rawData->status->label()} and can no longer be changed.",
            ]);
        }
    }
}
