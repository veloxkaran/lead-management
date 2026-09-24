<?php

namespace App\Http\Controllers;

use App\Enums\FollowUpStatus;
use App\Enums\RequirementStatus;
use App\Enums\TaskStatus;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\DailySummary;
use App\Models\DealClosure;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadStatus;
use App\Models\Meeting;
use App\Models\ReleaseNote;
use App\Models\Requirement;
use App\Models\RolePlaybook;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\User;
use App\Support\BsDate;
use App\Support\MotivationQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return match (true) {
            $user->isSuperAdmin() => $this->superAdminDashboard($user),
            $user->isManager() => $this->managerDashboard($user),
            $user->isCustomerSuccess() => $this->customerSuccessDashboard($user),
            $user->isFinance() => $this->financeDashboard($user),
            default => $this->businessDevelopmentDashboard($user),
        };
    }

    /**
     * JSON backing the "Performance Snapshot" dashboard widget
     * (resources/js/performance-snapshot.js + x-performance-snapshot).
     * Fetched client-side on load and on every Daily/Monthly/Lifetime
     * switch — the widget never triggers a page navigation, so switching
     * periods can't jump the user's scroll position. Identical for every
     * role, so it lives outside the per-role dashboard methods entirely
     * rather than being merged into their view data.
     */
    public function performanceSnapshotJson(Request $request): JsonResponse
    {
        return response()->json($this->performanceSnapshot($request->only('snapshot_period')));
    }

    /**
     * Shared greeting data every role dashboard renders above its own
     * content: role playbook (motivation), the rotating motivational quote,
     * and the org-wide average solving time for support tickets and
     * requirements, plus the latest announcements — highlighted on every dashboard rather than just the
     * role-specific ones, so it's one query per metric here instead of
     * being repeated in each role's method below.
     */
    protected function greeting(User $user): array
    {
        return [
            'user' => $user,
            'playbook' => RolePlaybook::forRole($user->role),
            'quote' => MotivationQuote::current(),
            'avgSupportTicketResolutionTime' => SupportTicket::averageResolutionFormatted(),
            'avgRequirementResolutionTime' => Requirement::averageResolutionFormatted(),
            'announcements' => Announcement::with('creator', 'images')->withCount('documents')->latest()->limit(5)->get(),
        ];
    }

    /**
     * "Performance Snapshot" data — identical for every role, which is why
     * it's fetched through its own JSON endpoint (performanceSnapshotJson())
     * instead of being merged into each role dashboard's view data. Three
     * periods: Daily (today), Monthly (this calendar month), and Lifetime
     * (unbounded — averageResolutionFormatted()/count() with no from/to
     * naturally means "every record ever" without any special-casing).
     */
    protected function performanceSnapshot(array $filters): array
    {
        $period = in_array($filters['snapshot_period'] ?? null, ['monthly', 'lifetime'], true)
            ? $filters['snapshot_period']
            : 'daily';

        [$from, $to, $dateLabel] = match ($period) {
            // The "Monthly" label is a BS month name, so the query range
            // must be that BS month's actual AD date span — BS/AD month
            // boundaries don't line up, so now()->startOfMonth()/endOfMonth()
            // (an AD calendar month) would silently aggregate the wrong ~2
            // weeks of data for whatever BS month is currently showing.
            'monthly' => $this->currentBsMonthRange(),
            'lifetime' => [null, null, BsDate::sinceLabel($this->earliestActivityDate())],
            default => [now()->startOfDay(), now()->endOfDay(), BsDate::dayLabel(now())],
        };

        $countBetween = function (string $model, string $column) use ($from, $to) {
            // whereNotNull matters most for the Lifetime period (no
            // from/to): without it this fell back to a bare count() of
            // every row — e.g. "Solved" tickets showing the total ticket
            // count instead of only ones with resolved_at set. For
            // Daily/Monthly it's already implied by whereBetween (SQL
            // BETWEEN on a NULL column is never true), but stating it
            // explicitly keeps both branches consistent.
            $query = $model::query()->whereNotNull($column);

            if ($from && $to) {
                $query->whereBetween($column, [$from, $to]);
            }

            return $query->count();
        };

        $leadsGenerated = $countBetween(Lead::class, 'created_at');
        $leadsConverted = $countBetween(Lead::class, 'achieved_at');

        return [
            'period' => $period,
            'dateLabel' => $dateLabel,
            'tickets' => [
                'created' => $countBetween(SupportTicket::class, 'created_at'),
                'solved' => $countBetween(SupportTicket::class, 'resolved_at'),
                'avgTime' => SupportTicket::averageResolutionFormatted($from, $to),
            ],
            'requirements' => [
                'created' => $countBetween(Requirement::class, 'created_at'),
                'closed' => $countBetween(Requirement::class, 'completed_at'),
                'avgTime' => Requirement::averageResolutionFormatted($from, $to),
            ],
            'leads' => [
                'generated' => $leadsGenerated,
                'converted' => $leadsConverted,
                'ratio' => $leadsGenerated > 0 ? round(($leadsConverted / $leadsGenerated) * 100, 1) : null,
            ],
        ];
    }

    /**
     * The current BS month's AD date span plus its label, e.g. today being
     * 2026-09-21 AD is BS "Asoj" — whose AD range is 2026-09-17 to
     * 2026-10-17, not the AD calendar month (2026-09-01 to 2026-09-30).
     * Mirrors LeadRepository's bs_year/bs_month filter, which resolves the
     * same way via BsDate::monthToAdRange().
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    protected function currentBsMonthRange(): array
    {
        $bs = BsDate::toBsParts(now());

        [$start, $end] = BsDate::monthToAdRange($bs['year'], $bs['month']);

        return [$start, $end, BsDate::MONTHS[$bs['month']]];
    }

    /**
     * Anchor date for the Lifetime snapshot's "since ..." label — the
     * earliest activity across the three tracked entities, or now() if the
     * system has no data yet at all.
     */
    protected function earliestActivityDate(): Carbon
    {
        $earliest = array_filter([
            Lead::min('created_at'),
            SupportTicket::min('created_at'),
            Requirement::min('created_at'),
        ]);

        return $earliest ? Carbon::parse(min($earliest)) : now();
    }

    protected function businessDevelopmentDashboard(User $user): View
    {
        $leads = Lead::where('assigned_user_id', $user->id)->active();

        $statusSummary = (clone $leads)->selectRaw('lead_status_id, count(*) as total')
            ->groupBy('lead_status_id')
            ->with('status')
            ->get();

        return view('dashboard.business-development', $this->greeting($user) + [
            'personalLeads' => (clone $leads)->latest()->take(6)->get(),
            'statusSummary' => $statusSummary,
            'todaysReminders' => FollowUp::whereHas('lead', fn ($q) => $q->where('assigned_user_id', $user->id))
                ->whereDate('follow_up_date', now()->toDateString())
                ->with('lead')->get(),
            'upcomingFollowUps' => FollowUp::whereHas('lead', fn ($q) => $q->where('assigned_user_id', $user->id))
                ->whereBetween('follow_up_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
                ->where('status', FollowUpStatus::Pending)
                ->with('lead')->orderBy('follow_up_date')->orderBy('follow_up_time')->take(8)->get(),
            'recentNotes' => LeadNote::whereHas('lead', fn ($q) => $q->where('assigned_user_id', $user->id))
                ->where('author_id', '!=', $user->id)
                ->with(['lead', 'author'])->latest()->take(5)->get(),
            'todaysSummarySubmitted' => DailySummary::where('user_id', $user->id)->whereDate('summary_date', now()->toDateString())->exists(),
            'recentSummaries' => DailySummary::where('user_id', $user->id)->latest('summary_date')->take(5)->get(),
            'meetings' => Meeting::where('created_by', $user->id)
                ->where('meeting_date', '>=', now()->toDateString())->orderBy('meeting_date')->orderBy('meeting_time')->take(5)->get(),
        ]);
    }

    protected function managerDashboard(User $user): View
    {
        $statusDistribution = LeadStatus::withCount(['leads' => fn ($q) => $q->active()])->ordered()->get();

        $monthlyConversion = DealClosure::selectRaw("strftime('%Y-%m', closed_date) as month, count(*) as total, sum(deal_value) as value")
            ->groupBy('month')->orderBy('month')->get()->slice(-6)->values();

        return view('dashboard.manager', $this->greeting($user) + [
            'totalLeads' => Lead::active()->count(),
            'dealStats' => [
                'count' => DealClosure::count(),
                'value' => DealClosure::sum('deal_value'),
                'thisMonth' => DealClosure::whereMonth('closed_date', now()->month)->whereYear('closed_date', now()->year)->sum('deal_value'),
            ],
            'statusDistribution' => $statusDistribution,
            'monthlyConversion' => $monthlyConversion,
            'openSupportTickets' => SupportTicket::whereNotIn('status', [RequirementStatus::Completed])->count(),
            'recentSupportTickets' => SupportTicket::with(['lead', 'raiser'])->latest()->take(5)->get(),
            'meetings' => Meeting::where('meeting_date', '>=', now()->toDateString())->orderBy('meeting_date')->orderBy('meeting_time')->take(6)->get(),
        ]);
    }

    protected function customerSuccessDashboard(User $user): View
    {
        return view('dashboard.customer-success', $this->greeting($user) + [
            'pendingTickets' => SupportTicket::where('status', RequirementStatus::Pending)->count(),
            'ticketQueue' => SupportTicket::whereNotIn('status', [RequirementStatus::Completed])
                ->with(['lead', 'raiser', 'assignee'])->oldest()->take(8)->get(),
        ]);
    }

    protected function financeDashboard(User $user): View
    {
        return view('dashboard.finance', $this->greeting($user));
    }

    protected function superAdminDashboard(User $user): View
    {
        $statusDistribution = LeadStatus::withCount(['leads' => fn ($q) => $q->active()])->ordered()->get();

        $monthlyConversion = DealClosure::selectRaw("strftime('%Y-%m', closed_date) as month, count(*) as total, sum(deal_value) as value")
            ->groupBy('month')->orderBy('month')->get()->slice(-6)->values();

        return view('dashboard.super-admin', $this->greeting($user) + [
            'totalLeads' => Lead::active()->count(),
            'totalUsers' => User::count(),
            'openTasks' => Task::whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])->count(),
            'openRequirements' => Requirement::whereNotIn('status', [RequirementStatus::Completed])->count(),
            'dealStats' => [
                'count' => DealClosure::count(),
                'value' => DealClosure::sum('deal_value'),
                'thisMonth' => DealClosure::whereMonth('closed_date', now()->month)->whereYear('closed_date', now()->year)->sum('deal_value'),
            ],
            'reminderSummary' => [
                'today' => FollowUp::whereDate('follow_up_date', now()->toDateString())->count(),
                'overdue' => FollowUp::due()->count(),
            ],
            'recentNotes' => LeadNote::with(['lead', 'author'])->latest()->take(6)->get(),
            'productivity' => [
                'submitted' => DailySummary::whereDate('summary_date', now()->toDateString())->count(),
                'total' => User::where('status', UserStatus::Active)->count(),
            ],
            'latestRelease' => ReleaseNote::latest('release_date')->first(),
            'meetings' => Meeting::where('meeting_date', '>=', now()->toDateString())->orderBy('meeting_date')->orderBy('meeting_time')->take(6)->get(),
            'statusDistribution' => $statusDistribution,
            'monthlyConversion' => $monthlyConversion,
            'openSupportTickets' => SupportTicket::whereNotIn('status', [RequirementStatus::Completed])->count(),
        ]);
    }
}
