<?php

namespace App\Http\Controllers;

use App\Enums\FollowUpStatus;
use App\Enums\RawDataStatus;
use App\Enums\RequirementStatus;
use App\Enums\TaskStatus;
use App\Enums\UserStatus;
use App\Models\DailySummary;
use App\Models\DealClosure;
use App\Models\FollowUp;
use App\Models\Goal;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadStatus;
use App\Models\Meeting;
use App\Models\RawData;
use App\Models\ReleaseNote;
use App\Models\Requirement;
use App\Models\RolePlaybook;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\User;
use App\Support\MotivationQuote;
use App\Support\PeriodRange;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['period', 'date_from', 'date_to']);

        return match (true) {
            $user->isSuperAdmin() => $this->superAdminDashboard($user, $filters),
            $user->isManager() => $this->managerDashboard($user, $filters),
            $user->isCustomerSuccess() => $this->customerSuccessDashboard($user, $filters),
            $user->isFinance() => $this->financeDashboard($user, $filters),
            default => $this->businessDevelopmentDashboard($user, $filters),
        };
    }

    /**
     * Shared greeting data every role dashboard renders above its own
     * content: role playbook (motivation), the rotating motivational quote,
     * and the org-wide average solving time for support tickets and
     * requirements — highlighted on every dashboard rather than just the
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
        ];
    }

    /**
     * "What's New Today?" — also shared across every role dashboard, same
     * reasoning as greeting(). Defaults to today but accepts the same
     * period/date_from/date_to vocabulary as the Raw Data filter
     * (see PeriodRange), so switching to "This Week" or a custom range
     * re-scopes all four counts at once.
     */
    protected function whatsNewToday(array $filters): array
    {
        [$from, $to] = PeriodRange::resolve($filters, 'today');

        // A "custom" period with no bounds filled in yet has nothing to
        // scope these counts to — fall back to today rather than silently
        // running them unbounded, since that would defeat the point of a
        // "today" widget.
        if (! $from || ! $to) {
            [$from, $to] = [now()->startOfDay(), now()->endOfDay()];
        }

        $newLeadsByStatus = LeadStatus::withCount(['leads' => fn ($q) => $q->whereBetween('created_at', [$from, $to])])
            ->ordered()->get()->filter(fn (LeadStatus $status) => $status->leads_count > 0)->values();

        return [
            'whatsNewFilters' => [
                'period' => $filters['period'] ?? 'today',
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
            'newLeadsByStatus' => $newLeadsByStatus,
            'newRawDataCount' => RawData::whereBetween('created_at', [$from, $to])->count(),
            // Scoped by converted_at (when the conversion happened), not
            // created_at — a raw-data entry created weeks ago and converted
            // today belongs in *today's* count, not the day it was created.
            'convertedRawDataCount' => RawData::where('status', RawDataStatus::ConvertedToLead)
                ->whereBetween('converted_at', [$from, $to])->count(),
            'ticketsRaisedCount' => SupportTicket::whereBetween('created_at', [$from, $to])->count(),
            'ticketsSolvedCount' => SupportTicket::whereBetween('resolved_at', [$from, $to])->count(),
        ];
    }

    protected function businessDevelopmentDashboard(User $user, array $filters): View
    {
        $leads = Lead::where('assigned_user_id', $user->id)->active();

        $statusSummary = (clone $leads)->selectRaw('lead_status_id, count(*) as total')
            ->groupBy('lead_status_id')
            ->with('status')
            ->get();

        return view('dashboard.business-development', $this->greeting($user) + $this->whatsNewToday($filters) + [
            'personalLeads' => (clone $leads)->latest()->take(6)->get(),
            'statusSummary' => $statusSummary,
            'todaysReminders' => FollowUp::whereHas('lead', fn ($q) => $q->where('assigned_user_id', $user->id))
                ->whereDate('follow_up_date', now()->toDateString())
                ->with('lead')->get(),
            'upcomingFollowUps' => FollowUp::whereHas('lead', fn ($q) => $q->where('assigned_user_id', $user->id))
                ->whereBetween('follow_up_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
                ->where('status', FollowUpStatus::Pending)
                ->with('lead')->orderBy('follow_up_date')->orderBy('follow_up_time')->take(8)->get(),
            'organizationGoals' => Goal::latest()->get(),
            'recentNotes' => LeadNote::whereHas('lead', fn ($q) => $q->where('assigned_user_id', $user->id))
                ->where('author_id', '!=', $user->id)
                ->with(['lead', 'author'])->latest()->take(5)->get(),
            'todaysSummarySubmitted' => DailySummary::where('user_id', $user->id)->whereDate('summary_date', now()->toDateString())->exists(),
            'recentSummaries' => DailySummary::where('user_id', $user->id)->latest('summary_date')->take(5)->get(),
            'meetings' => Meeting::where('created_by', $user->id)
                ->where('meeting_date', '>=', now()->toDateString())->orderBy('meeting_date')->orderBy('meeting_time')->take(5)->get(),
        ]);
    }

    protected function managerDashboard(User $user, array $filters): View
    {
        $statusDistribution = LeadStatus::withCount(['leads' => fn ($q) => $q->active()])->ordered()->get();

        $monthlyConversion = DealClosure::selectRaw("strftime('%Y-%m', closed_date) as month, count(*) as total, sum(deal_value) as value")
            ->groupBy('month')->orderBy('month')->get()->slice(-6)->values();

        return view('dashboard.manager', $this->greeting($user) + $this->whatsNewToday($filters) + [
            'totalLeads' => Lead::active()->count(),
            'dealStats' => [
                'count' => DealClosure::count(),
                'value' => DealClosure::sum('deal_value'),
                'thisMonth' => DealClosure::whereMonth('closed_date', now()->month)->whereYear('closed_date', now()->year)->sum('deal_value'),
            ],
            'organizationGoals' => Goal::latest()->get(),
            'statusDistribution' => $statusDistribution,
            'monthlyConversion' => $monthlyConversion,
            'openSupportTickets' => SupportTicket::whereNotIn('status', [RequirementStatus::Completed])->count(),
            'recentSupportTickets' => SupportTicket::with(['lead', 'raiser'])->latest()->take(5)->get(),
            'meetings' => Meeting::where('meeting_date', '>=', now()->toDateString())->orderBy('meeting_date')->orderBy('meeting_time')->take(6)->get(),
        ]);
    }

    protected function customerSuccessDashboard(User $user, array $filters): View
    {
        return view('dashboard.customer-success', $this->greeting($user) + $this->whatsNewToday($filters) + [
            'organizationGoals' => Goal::latest()->get(),
            'pendingTickets' => SupportTicket::where('status', RequirementStatus::Pending)->count(),
            'ticketQueue' => SupportTicket::whereNotIn('status', [RequirementStatus::Completed])
                ->with(['lead', 'raiser', 'assignee'])->oldest()->take(8)->get(),
        ]);
    }

    protected function financeDashboard(User $user, array $filters): View
    {
        return view('dashboard.finance', $this->greeting($user) + $this->whatsNewToday($filters) + [
            'organizationGoals' => Goal::latest()->get(),
        ]);
    }

    protected function superAdminDashboard(User $user, array $filters): View
    {
        $statusDistribution = LeadStatus::withCount(['leads' => fn ($q) => $q->active()])->ordered()->get();

        $monthlyConversion = DealClosure::selectRaw("strftime('%Y-%m', closed_date) as month, count(*) as total, sum(deal_value) as value")
            ->groupBy('month')->orderBy('month')->get()->slice(-6)->values();

        return view('dashboard.super-admin', $this->greeting($user) + $this->whatsNewToday($filters) + [
            'totalLeads' => Lead::active()->count(),
            'totalUsers' => User::count(),
            'openTasks' => Task::whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])->count(),
            'openRequirements' => Requirement::whereNotIn('status', [RequirementStatus::Completed])->count(),
            'dealStats' => [
                'count' => DealClosure::count(),
                'value' => DealClosure::sum('deal_value'),
                'thisMonth' => DealClosure::whereMonth('closed_date', now()->month)->whereYear('closed_date', now()->year)->sum('deal_value'),
            ],
            'organizationGoals' => Goal::latest()->get(),
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
