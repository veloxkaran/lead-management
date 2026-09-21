<?php

namespace App\Services;

use App\Enums\ActivityModule;
use App\Enums\RequirementStatus;
use App\Events\RequirementSaved;
use App\Models\ActivityLogEntry;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\RequirementComment;
use App\Models\User;
use App\Repositories\RequirementRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Mews\Purifier\Facades\Purifier;

class RequirementService
{
    public function __construct(
        protected RequirementRepository $requirements,
        protected ClientNotifier $notifier,
    ) {
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->requirements->filter($filters, $perPage);
    }

    public function listGroupedByCompany(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->requirements->groupedByCompany($filters, $perPage);
    }

    /**
     * @return Collection<int, Requirement>
     */
    public function listForCompany(Lead $lead): Collection
    {
        return $this->requirements->forLead($lead);
    }

    /**
     * @return Collection<int, Requirement>
     */
    public function listAllForExport(array $filters): Collection
    {
        return $this->requirements->allFiltered($filters);
    }

    /**
     * @param  array<int, UploadedFile|null>  $files
     */
    public function create(array $attributes, User $creator, array $files = []): Requirement
    {
        $attributes['created_by'] = $creator->id;

        if (array_key_exists('requirement', $attributes)) {
            $attributes['requirement'] = Purifier::clean($attributes['requirement']);
        }

        /** @var Requirement $requirement */
        $requirement = $this->requirements->create($attributes);

        $this->storeAttachments($requirement, $files);

        $this->notifier->notify('requirement_created', $requirement->lead?->email, [
            ...$this->clientVariables($requirement),
            'due_date' => $requirement->due_date?->format('M d, Y'),
        ], $requirement);

        event(new RequirementSaved($requirement, true));

        return $requirement;
    }

    /**
     * @param  array<int, UploadedFile|null>  $files
     */
    public function createForLead(Lead $lead, array $attributes, User $creator, array $files = []): Requirement
    {
        $attributes['lead_id'] = $lead->id;

        return $this->create($attributes, $creator, $files);
    }

    /**
     * Diffs before fill()+save() (getDirty(), not getChanges()) so the log
     * is correct regardless of anything downstream touching the model
     * afterward — same reasoning as TaskService::update(). Every field
     * change is logged, not just due_date, since there's no reason a
     * priority/status/assignment change should be any less auditable.
     */
    /**
     * @param  array<int, UploadedFile|null>  $files
     */
    public function update(Requirement $requirement, array $attributes, User $actor, ?string $ip, ?string $userAgent, array $files = []): Requirement
    {
        if (($attributes['status'] ?? null) === RequirementStatus::Completed->value && ! $requirement->completed_at) {
            $attributes['completed_at'] = now();
        }

        if (array_key_exists('requirement', $attributes)) {
            $attributes['requirement'] = Purifier::clean($attributes['requirement']);
        }

        $originalRaw = collect(array_keys($attributes))
            ->mapWithKeys(fn ($key) => [$key => $requirement->getRawOriginal($key)])
            ->all();

        $requirement->fill($attributes);
        $changed = $requirement->getDirty();
        unset($changed['updated_at']);

        $requirement->save();

        $this->storeAttachments($requirement, $files);

        if (! empty($changed)) {
            $this->logChange($requirement, $actor, $ip, $userAgent, array_intersect_key($originalRaw, $changed), $changed);
        }

        if (array_key_exists('status', $changed)) {
            $this->notifier->notify('requirement_status_changed', $requirement->lead?->email, [
                ...$this->clientVariables($requirement),
                'old_status' => RequirementStatus::from($originalRaw['status'])->label(),
                'new_status' => $requirement->status->label(),
            ], $requirement);
        }

        event(new RequirementSaved($requirement, false));

        return $requirement;
    }

    /**
     * @return array<string, string|null>
     */
    private function clientVariables(Requirement $requirement): array
    {
        return [
            'company_name' => $requirement->lead?->company_name,
            'contact_person' => $requirement->lead?->contact_person,
            // The email body is escaped+nl2br'd, not rendered as HTML (see
            // emails/client-notification.blade.php), so the rich-text
            // requirement content is flattened to plain text here rather
            // than leaking raw tags into the sent email.
            'requirement' => trim(strip_tags($requirement->requirement)),
            'priority' => $requirement->priority->label(),
        ];
    }

    /**
     * The list page's quick "Status" popup: changes only the status field
     * (reusing update() so completed_at/activity-log/RequirementSaved all
     * behave exactly as they do from the full edit form), and requires a
     * note explaining the change — recorded in the same comment thread as
     * addComment(), prefixed so it reads distinctly from a plain comment.
     */
    public function updateStatus(Requirement $requirement, string $status, string $note, User $actor, ?string $ip, ?string $userAgent): Requirement
    {
        $requirement = $this->update($requirement, ['status' => $status], $actor, $ip, $userAgent);

        $this->addComment($requirement, [
            'comment' => "Status changed to {$requirement->status->label()}: {$note}",
        ], $actor);

        return $requirement;
    }

    public function delete(Requirement $requirement): void
    {
        $this->requirements->delete($requirement);
    }

    public function addComment(Requirement $requirement, array $attributes, User $author): RequirementComment
    {
        return $requirement->comments()->create([
            ...$attributes,
            'author_id' => $author->id,
        ]);
    }

    /**
     * Shared by create() and update() — files can be attached when a
     * requirement is logged and appended any time afterward, mirroring
     * SupportTicketService's storeAttachments() pattern.
     *
     * @param  array<int, UploadedFile|null>  $files
     */
    private function storeAttachments(Requirement $requirement, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store("requirements/{$requirement->id}", 'public');

            $requirement->attachments()->create([
                'disk_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    private function logChange(Requirement $requirement, User $actor, ?string $ip, ?string $userAgent, array $oldValues, array $newValues): void
    {
        ActivityLogEntry::create([
            'company_id' => $requirement->company_id ?? $actor->company_id,
            'user_id' => $actor->id,
            'module' => ActivityModule::Requirement,
            'description' => "updated requirement for {$requirement->lead?->company_name}",
            'subject_type' => $requirement->getMorphClass(),
            'subject_id' => $requirement->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
