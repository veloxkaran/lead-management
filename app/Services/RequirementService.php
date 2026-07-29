<?php

namespace App\Services;

use App\Enums\ActivityModule;
use App\Events\RequirementSaved;
use App\Models\ActivityLogEntry;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\RequirementComment;
use App\Models\User;
use App\Repositories\RequirementRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class RequirementService
{
    public function __construct(protected RequirementRepository $requirements)
    {
    }

    public function list(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->requirements->filter($filters, $perPage);
    }

    /**
     * @return Collection<int, Requirement>
     */
    public function listAllForExport(array $filters): Collection
    {
        return $this->requirements->allFiltered($filters);
    }

    public function create(array $attributes, User $creator): Requirement
    {
        $attributes['created_by'] = $creator->id;

        if (! empty($attributes['adopted_by'])) {
            $attributes['adopted_at'] = now();
        }

        /** @var Requirement $requirement */
        $requirement = $this->requirements->create($attributes);

        event(new RequirementSaved($requirement, true));

        return $requirement;
    }

    public function createForLead(Lead $lead, array $attributes, User $creator): Requirement
    {
        $attributes['lead_id'] = $lead->id;

        return $this->create($attributes, $creator);
    }

    /**
     * Diffs before fill()+save() (getDirty(), not getChanges()) so the log
     * is correct regardless of anything downstream touching the model
     * afterward — same reasoning as TaskService::update(). Every field
     * change is logged, not just due_date, since there's no reason a
     * priority/status/assignment change should be any less auditable.
     */
    public function update(Requirement $requirement, array $attributes, User $actor, ?string $ip, ?string $userAgent): Requirement
    {
        // adopted_at isn't user-editable — it tracks the moment adopted_by last
        // changed, set here (not on the form) so it can't drift from that event.
        if (array_key_exists('adopted_by', $attributes) && $attributes['adopted_by'] != $requirement->adopted_by) {
            $attributes['adopted_at'] = $attributes['adopted_by'] ? now() : null;
        }

        $originalRaw = collect(array_keys($attributes))
            ->mapWithKeys(fn ($key) => [$key => $requirement->getRawOriginal($key)])
            ->all();

        $requirement->fill($attributes);
        $changed = $requirement->getDirty();
        unset($changed['updated_at']);

        $requirement->save();

        if (! empty($changed)) {
            $this->logChange($requirement, $actor, $ip, $userAgent, array_intersect_key($originalRaw, $changed), $changed);
        }

        event(new RequirementSaved($requirement, false));

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
