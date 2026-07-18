<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\User;
use App\Repositories\MeetingRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class MeetingService
{
    public function __construct(protected MeetingRepository $meetings) {}

    /**
     * Scope options: "mine" (default: meetings the user created), "all" (Manager/Super Admin only).
     */
    public function list(User $user, string $scope = 'mine', int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->meetings->query()->with('creator');

        if ($scope !== 'all' || ! $user->isOverseer()) {
            $query->where('created_by', $user->id);
        }

        return $query->orderByDesc('meeting_date')->orderByDesc('meeting_time')->paginate($perPage)->withQueryString();
    }

    public function create(array $attributes, User $creator): Meeting
    {
        $attributes['created_by'] = $creator->id;
        $attributes['participants'] = $this->parseParticipants($attributes['participants'] ?? null);

        return $this->meetings->create($attributes);
    }

    public function update(Meeting $meeting, array $attributes): Meeting
    {
        $attributes['participants'] = $this->parseParticipants($attributes['participants'] ?? null);

        return $this->meetings->update($meeting, $attributes);
    }

    public function delete(Meeting $meeting): bool
    {
        return $this->meetings->delete($meeting);
    }

    protected function parseParticipants(?string $raw): array
    {
        if (! $raw) {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/', $raw))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
