<?php

namespace App\Services;

use App\Enums\RawDataStatus;
use App\Models\Lead;
use App\Models\RawData;
use App\Models\RawDataComment;
use App\Models\User;
use App\Repositories\RawDataRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        $this->guardIsNew($rawData);

        $rawData->update(['status' => RawDataStatus::NotValid]);

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
        $this->guardIsNew($rawData);

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
     */
    public function findExistingForImportRow(string $contactPerson, string $phone): ?RawData
    {
        return $this->rawData->query()->whereRaw('lower(phone) = ?', [mb_strtolower(trim($phone))])->first()
            ?? $this->rawData->query()->whereRaw('lower(contact_person) = ?', [mb_strtolower(trim($contactPerson))])->first();
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

        foreach (['email', 'source'] as $field) {
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

    private function guardIsNew(RawData $rawData): void
    {
        if (! $rawData->isNew()) {
            throw ValidationException::withMessages([
                'status' => "This entry is already {$rawData->status->label()} and can no longer be changed.",
            ]);
        }
    }
}
