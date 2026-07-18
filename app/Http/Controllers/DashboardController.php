<?php

namespace App\Http\Controllers;

use App\Enums\RequirementStatus;
use App\Models\AccountRequest;
use App\Models\DailySummary;
use App\Models\DealClosure;
use App\Models\FollowUp;
use App\Models\Goal;
use App\Models\ImplementationRequest;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadStatus;
use App\Models\Meeting;
use App\Models\ReleaseNote;
use App\Models\RolePlaybook;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\MotivationQuote;
use Illuminate\Http\Request;
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
     * Shared greeting data every role dashboard renders above its own
     * content: role playbook (responsibilities/SOPs/success matrix/
     * motivation) plus the rotating motivational quote.
     */
    protected function greeting(User $user): array
    {
        return [
            'user' => $user,
            'playbook' => RolePlaybook::forRole($user->role),
            'quote' => MotivationQuote::current(),
        ];
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
                ->where('status', \App\Enums\FollowUpStatus::Pending)
                ->with('lead')->orderBy('follow_up_date')->orderBy('follow_up_time')->take(8)->get(),
            'individualGoals' => Goal::where('goal_type', \App\Enums\GoalType::Individual)->where('user_id', $user->id)->latest()->get(),
            'recentNotes' => LeadNote::whereHas('lead', fn ($q) => $q->where('assigned_user_id', $user->id))
                ->where('author_id', '!=', $user->id)
                ->with(['lead', 'author'])->latest()->take(5)->get(),
            'todaysSummarySubmitted' => DailySummary::where('user_id', $user->id)->whereDate('summary_date', now()->toDateString())->exists(),
            'recentSummaries' => DailySummary::where('user_id', $user->id)->latest('summary_date')->take(5)->get(),
            'meetings' => Meeting::where('created_by', $user->id)
                ->where('meeting_date', '>=', now()->toDateString())->orderBy('meeting_date')->orderBy('meeting_time')->take(5)->get(),
            'myImplementationRequests' => ImplementationRequest::where('requested_by', $user->id)->with('lead')->latest()->take(5)->get(),
            'myAccountRequests' => AccountRequest::where('requested_by', $user->id)->with('lead')->latest()->take(5)->get(),
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
            'organizationGoals' => Goal::where('goal_type', \App\Enums\GoalType::Organization)->latest()->get(),
            'statusDistribution' => $statusDistribution,
            'monthlyConversion' => $monthlyConversion,
            'openImplementationRequests' => ImplementationRequest::whereNotIn('status', [RequirementStatus::Completed])->count(),
            'openSupportTickets' => SupportTicket::whereNotIn('status', [RequirementStatus::Completed])->count(),
            'openAccountRequests' => AccountRequest::whereNotIn('status', [RequirementStatus::Completed])->count(),
            'recentImplementationRequests' => ImplementationRequest::with(['lead', 'requester'])->latest()->take(5)->get(),
            'recentSupportTickets' => SupportTicket::with(['lead', 'raiser'])->latest()->take(5)->get(),
            'meetings' => Meeting::where('meeting_date', '>=', now()->toDateString())->orderBy('meeting_date')->orderBy('meeting_time')->take(6)->get(),
        ]);
    }

    protected function customerSuccessDashboard(User $user): View
    {
        return view('dashboard.customer-success', $this->greeting($user) + [
            'pendingImplementations' => ImplementationRequest::where('status', RequirementStatus::Pending)->count(),
            'inProgressImplementations' => ImplementationRequest::where('status', RequirementStatus::InProgress)->count(),
            'completedThisMonth' => ImplementationRequest::where('status', RequirementStatus::Completed)
                ->whereMonth('completed_at', now()->month)->whereYear('completed_at', now()->year)->count(),
            'pendingTickets' => SupportTicket::where('status', RequirementStatus::Pending)->count(),
            'implementationQueue' => ImplementationRequest::whereNotIn('status', [RequirementStatus::Completed])
                ->with(['lead', 'requester', 'assignee'])->oldest()->take(8)->get(),
            'ticketQueue' => SupportTicket::whereNotIn('status', [RequirementStatus::Completed])
                ->with(['lead', 'raiser', 'assignee'])->oldest()->take(8)->get(),
        ]);
    }

    protected function financeDashboard(User $user): View
    {
        return view('dashboard.finance', $this->greeting($user) + [
            'pendingCount' => AccountRequest::where('status', RequirementStatus::Pending)->count(),
            'pendingAmount' => AccountRequest::whereNotIn('status', [RequirementStatus::Completed])->sum('amount'),
            'completedThisMonth' => AccountRequest::where('status', RequirementStatus::Completed)
                ->whereMonth('processed_at', now()->month)->whereYear('processed_at', now()->year)->sum('amount'),
            'requestQueue' => AccountRequest::whereNotIn('status', [RequirementStatus::Completed])
                ->with(['lead', 'requester'])->oldest()->take(10)->get(),
        ]);
    }

    protected function superAdminDashboard(User $user): View
    {
        $statusDistribution = LeadStatus::withCount(['leads' => fn ($q) => $q->active()])->ordered()->get();

        $monthlyConversion = DealClosure::selectRaw("strftime('%Y-%m', closed_date) as month, count(*) as total, sum(deal_value) as value")
            ->groupBy('month')->orderBy('month')->get()->slice(-6)->values();

        return view('dashboard.super-admin', $this->greeting($user) + [
            'totalLeads' => Lead::active()->count(),
            'totalUsers' => User::count(),
            'openRequirements' => \App\Models\Requirement::whereNotIn('status', [\App\Enums\RequirementStatus::Completed])->count(),
            'dealStats' => [
                'count' => DealClosure::count(),
                'value' => DealClosure::sum('deal_value'),
                'thisMonth' => DealClosure::whereMonth('closed_date', now()->month)->whereYear('closed_date', now()->year)->sum('deal_value'),
            ],
            'organizationGoals' => Goal::where('goal_type', \App\Enums\GoalType::Organization)->latest()->get(),
            'reminderSummary' => [
                'today' => FollowUp::whereDate('follow_up_date', now()->toDateString())->count(),
                'overdue' => FollowUp::due()->count(),
            ],
            'recentNotes' => LeadNote::with(['lead', 'author'])->latest()->take(6)->get(),
            'productivity' => [
                'submitted' => DailySummary::whereDate('summary_date', now()->toDateString())->count(),
                'total' => User::where('status', \App\Enums\UserStatus::Active)->count(),
            ],
            'latestRelease' => ReleaseNote::latest('release_date')->first(),
            'meetings' => Meeting::where('meeting_date', '>=', now()->toDateString())->orderBy('meeting_date')->orderBy('meeting_time')->take(6)->get(),
            'statusDistribution' => $statusDistribution,
            'monthlyConversion' => $monthlyConversion,
            'openImplementationRequests' => ImplementationRequest::whereNotIn('status', [RequirementStatus::Completed])->count(),
            'openSupportTickets' => SupportTicket::whereNotIn('status', [RequirementStatus::Completed])->count(),
            'openAccountRequests' => AccountRequest::whereNotIn('status', [RequirementStatus::Completed])->count(),
        ]);
    }
}
