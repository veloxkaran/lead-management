<?php

namespace App\Http\Controllers;

use App\Models\DailySummary;
use App\Models\DealClosure;
use App\Models\FollowUp;
use App\Models\Goal;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadStatus;
use App\Models\Meeting;
use App\Models\ReleaseNote;
use App\Models\User;
use App\Support\MotivationQuote;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $quote = MotivationQuote::current();

        if ($user->isSuperAdmin()) {
            return $this->superAdminDashboard($quote);
        }

        return $this->userDashboard($user, $quote);
    }

    protected function userDashboard(User $user, array $quote): View
    {
        $leads = Lead::where('assigned_user_id', $user->id)->active();

        $statusSummary = (clone $leads)->selectRaw('lead_status_id, count(*) as total')
            ->groupBy('lead_status_id')
            ->with('status')
            ->get();

        return view('dashboard.user', [
            'quote' => $quote,
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
            'teamGoals' => $user->team_id ? Goal::where('goal_type', \App\Enums\GoalType::Team)->where('team_id', $user->team_id)->latest()->get() : collect(),
            'recentNotes' => LeadNote::whereHas('lead', fn ($q) => $q->where('assigned_user_id', $user->id))
                ->where('author_id', '!=', $user->id)
                ->with(['lead', 'author'])->latest()->take(5)->get(),
            'todaysSummarySubmitted' => DailySummary::where('user_id', $user->id)->whereDate('summary_date', now()->toDateString())->exists(),
            'recentSummaries' => DailySummary::where('user_id', $user->id)->latest('summary_date')->take(5)->get(),
            'meetings' => Meeting::where(function ($q) use ($user) {
                $q->where('created_by', $user->id);
                if ($user->team_id) {
                    $q->orWhere('team_id', $user->team_id);
                }
            })->where('meeting_date', '>=', now()->toDateString())->orderBy('meeting_date')->orderBy('meeting_time')->take(5)->get(),
        ]);
    }

    protected function superAdminDashboard(array $quote): View
    {
        $statusDistribution = LeadStatus::withCount(['leads' => fn ($q) => $q->active()])->ordered()->get();

        $monthlyConversion = DealClosure::selectRaw("strftime('%Y-%m', closed_date) as month, count(*) as total, sum(deal_value) as value")
            ->groupBy('month')->orderBy('month')->get()->slice(-6)->values();

        return view('dashboard.super-admin', [
            'quote' => $quote,
            'totalLeads' => Lead::active()->count(),
            'totalUsers' => User::count(),
            'openRequirements' => \App\Models\Requirement::whereNotIn('status', [\App\Enums\RequirementStatus::Completed])->count(),
            'dealStats' => [
                'count' => DealClosure::count(),
                'value' => DealClosure::sum('deal_value'),
                'thisMonth' => DealClosure::whereMonth('closed_date', now()->month)->whereYear('closed_date', now()->year)->sum('deal_value'),
            ],
            'organizationGoals' => Goal::where('goal_type', \App\Enums\GoalType::Organization)->latest()->get(),
            'teamGoals' => Goal::where('goal_type', \App\Enums\GoalType::Team)->with('team')->latest()->get(),
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
        ]);
    }
}
