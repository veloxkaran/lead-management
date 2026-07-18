<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserHierarchyRepository extends BaseRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * @return Collection<int, User>
     */
    public function getDirectReports(User $manager): Collection
    {
        return $this->query()->where('reporting_manager_id', $manager->id)
            ->orderBy('name')->get();
    }

    /**
     * All (in)direct subordinate ids of $rootUserId in one query via a
     * recursive CTE, rather than N+1 recursive lookups. The depth cap is
     * defense-in-depth against a cycle slipping past
     * ProhibitsHierarchyCycles — SQLite's WITH RECURSIVE has no automatic
     * cycle detection and would otherwise loop until it exhausts memory.
     * Deliberately does not filter out soft-deleted managers from the join:
     * a suspended manager's still-active reports must remain visible to
     * *their* manager.
     *
     * @return Collection<int, int>
     */
    public function getAllSubordinateIds(int $rootUserId): Collection
    {
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE subordinates AS (
                SELECT id, reporting_manager_id, 0 AS depth
                FROM users
                WHERE id = ?

                UNION ALL

                SELECT u.id, u.reporting_manager_id, s.depth + 1
                FROM users u
                INNER JOIN subordinates s ON u.reporting_manager_id = s.id
                WHERE s.depth < 50
            )
            SELECT id FROM subordinates WHERE depth > 0
        SQL, [$rootUserId]);

        return collect($rows)->pluck('id');
    }

    /**
     * Flat rows for every user in the company (or every user if $companyId
     * is null), feeding OrganizationHierarchyService::getOrganizationTree()'s
     * in-PHP tree build — one query instead of one per node.
     *
     * @return Collection<int, object>
     */
    public function getAllRowsForTree(?int $companyId): Collection
    {
        return $this->query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->select(['id', 'name', 'email', 'role', 'designation', 'department_id', 'team_id', 'reporting_manager_id'])
            ->orderBy('name')
            ->get();
    }
}
