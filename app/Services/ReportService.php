<?php

namespace App\Services;

use App\Models\DailySummary;
use App\Models\DealClosure;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\Requirement;
use Illuminate\Support\Carbon;

class ReportService
{
    public function master(array $filters): array
    {
        $query = Lead::query()->with(['assignedUser', 'status', 'dealClosure']);
        $this->applyCommonFilters($query, $filters);

        $rows = $query->latest()->get()->map(fn (Lead $lead) => [
            'Company' => $lead->company_name,
            'Contact' => $lead->contact_person,
            'Status' => $lead->status?->name,
            'Assigned To' => $lead->assignedUser?->name,
            'Source' => $lead->source,
            'Created' => $lead->created_at->format('Y-m-d'),
            'Opportunity Cost' => $lead->opportunity_cost,
            'Achieved Cost' => $lead->achieved_cost,
            'Deal Value' => $lead->dealClosure?->deal_value,
        ]);

        return ['title' => 'Master Report', 'headings' => ['Company', 'Contact', 'Status', 'Assigned To', 'Source', 'Created', 'Opportunity Cost', 'Achieved Cost', 'Deal Value'], 'rows' => $rows];
    }

    public function daily(string $date): array
    {
        $day = Carbon::parse($date);

        $leads = Lead::whereDate('created_at', $day)->with('assignedUser')->get()->map(fn (Lead $l) => [
            'Type' => 'New Lead', 'Detail' => $l->company_name, 'By' => $l->creator?->name, 'Time' => $l->created_at->format('H:i'),
        ]);

        $activities = \App\Models\LeadActivity::whereDate('activity_date', $day)->with(['lead', 'creator'])->get()->map(fn ($a) => [
            'Type' => 'Activity: '.$a->activity_type->label(), 'Detail' => $a->lead->company_name.' — '.$a->description, 'By' => $a->creator?->name, 'Time' => $a->activity_time,
        ]);

        $summaries = DailySummary::whereDate('summary_date', $day)->with('user')->get()->map(fn ($s) => [
            'Type' => 'Daily Summary', 'Detail' => $s->achieved_today, 'By' => $s->user?->name, 'Time' => $s->created_at->format('H:i'),
        ]);

        $rows = $leads->concat($activities)->concat($summaries)->sortBy('Time')->values();

        return ['title' => 'Daily Report — '.$day->format('M d, Y'), 'headings' => ['Type', 'Detail', 'By', 'Time'], 'rows' => $rows];
    }

    public function monthly(string $month): array
    {
        return $this->periodReport(Carbon::parse($month.'-01')->startOfMonth(), Carbon::parse($month.'-01')->endOfMonth(), 'Monthly Report — '.Carbon::parse($month.'-01')->format('F Y'));
    }

    public function quarterly(int $year, int $quarter): array
    {
        $start = Carbon::create($year, ($quarter - 1) * 3 + 1, 1)->startOfMonth();
        $end = (clone $start)->addMonths(2)->endOfMonth();

        return $this->periodReport($start, $end, "Quarterly Report — Q{$quarter} {$year}");
    }

    protected function periodReport(Carbon $start, Carbon $end, string $title): array
    {
        $leadsCreated = Lead::whereBetween('created_at', [$start, $end])->count();
        $dealsClosed = DealClosure::whereBetween('closed_date', [$start, $end])->get();

        $byUser = Lead::whereBetween('leads.created_at', [$start, $end])
            ->selectRaw('assigned_user_id, count(*) as total')
            ->groupBy('assigned_user_id')->with('assignedUser')->get();

        $rows = $byUser->map(fn ($row) => [
            'User' => $row->assignedUser?->name ?? 'Unassigned',
            'Leads Created' => $row->total,
            'Deals Closed' => $dealsClosed->where('closed_by', $row->assigned_user_id)->count(),
            'Deal Value' => $dealsClosed->where('closed_by', $row->assigned_user_id)->sum('deal_value'),
        ]);

        return [
            'title' => $title,
            'headings' => ['User', 'Leads Created', 'Deals Closed', 'Deal Value'],
            'rows' => $rows,
            'summary' => [
                'Leads Created' => $leadsCreated,
                'Deals Closed' => $dealsClosed->count(),
                'Total Deal Value' => $dealsClosed->sum('deal_value'),
            ],
        ];
    }

    public function time(): array
    {
        $rows = LeadStatusHistory::whereNotNull('seconds_in_previous_status')
            ->selectRaw('from_status_id, avg(seconds_in_previous_status) as avg_seconds, count(*) as transitions')
            ->groupBy('from_status_id')->with('fromStatus')->get()
            ->filter(fn ($r) => $r->fromStatus)
            ->map(fn ($r) => [
                'Status' => $r->fromStatus->name,
                'Avg Time Spent' => gmdate('H:i:s', (int) $r->avg_seconds).' (hh:mm:ss)',
                'Transitions Out' => $r->transitions,
            ]);

        return ['title' => 'Time Report', 'headings' => ['Status', 'Avg Time Spent', 'Transitions Out'], 'rows' => $rows];
    }

    public function opportunity(): array
    {
        $rows = Lead::active()->whereHas('status', fn ($q) => $q->where('is_closed_won', false)->where('is_closed_lost', false))
            ->with(['status', 'assignedUser'])->get()->map(fn (Lead $l) => [
                'Company' => $l->company_name, 'Status' => $l->status?->name, 'Assigned To' => $l->assignedUser?->name,
                'Opportunity Cost' => $l->opportunity_cost, 'Age (days)' => $l->created_at->diffInDays(now()),
            ]);

        return ['title' => 'Opportunity Report', 'headings' => ['Company', 'Status', 'Assigned To', 'Opportunity Cost', 'Age (days)'], 'rows' => $rows, 'summary' => ['Total Opportunity Cost' => $rows->sum('Opportunity Cost')]];
    }

    public function failure(): array
    {
        $rows = Lead::where(function ($q) {
            $q->whereHas('status', fn ($sq) => $sq->where('is_closed_lost', true))
                ->orWhere(function ($sq) {
                    $sq->whereNotNull('archived_at')->whereDoesntHave('dealClosure');
                });
        })->with(['status', 'assignedUser'])->get()->map(fn (Lead $l) => [
            'Company' => $l->company_name, 'Status' => $l->status?->name, 'Assigned To' => $l->assignedUser?->name, 'Archived' => $l->archived_at?->format('Y-m-d') ?? '—',
        ]);

        return ['title' => 'Failure Report', 'headings' => ['Company', 'Status', 'Assigned To', 'Archived'], 'rows' => $rows];
    }

    public function deal(array $filters): array
    {
        $query = DealClosure::query()->with(['lead', 'closedBy']);

        if (! empty($filters['from'])) {
            $query->whereDate('closed_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('closed_date', '<=', $filters['to']);
        }

        $rows = $query->latest('closed_date')->get()->map(fn (DealClosure $d) => [
            'Company' => $d->lead->company_name, 'Closed By' => $d->closedBy?->name, 'Closed Date' => $d->closed_date->format('Y-m-d'), 'Value' => $d->deal_value,
        ]);

        return ['title' => 'Deal Report', 'headings' => ['Company', 'Closed By', 'Closed Date', 'Value'], 'rows' => $rows, 'summary' => ['Total Value' => $rows->sum('Value')]];
    }

    public function requirement(array $filters): array
    {
        $query = Requirement::query()->with(['lead', 'assignee']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $rows = $query->latest()->get()->map(fn (Requirement $r) => [
            'Company' => $r->lead->company_name, 'Requirement' => $r->requirement, 'Priority' => $r->priority->label(), 'Status' => $r->status->label(), 'Assigned To' => $r->assignee?->name,
        ]);

        return ['title' => 'Requirement Report', 'headings' => ['Company', 'Requirement', 'Priority', 'Status', 'Assigned To'], 'rows' => $rows];
    }

    public function conversion(): array
    {
        $rows = DealClosure::selectRaw("strftime('%Y-%m', closed_date) as month, count(*) as deals, sum(deal_value) as value")
            ->groupBy('month')->orderByDesc('month')->get()->map(fn ($r) => [
                'Month' => $r->month, 'Deals Closed' => $r->deals, 'Total Value' => $r->value,
            ]);

        return ['title' => 'Conversion Report', 'headings' => ['Month', 'Deals Closed', 'Total Value'], 'rows' => $rows];
    }

    protected function applyCommonFilters($query, array $filters): void
    {
        if (! empty($filters['user_id'])) {
            $query->where('assigned_user_id', $filters['user_id']);
        }
        if (! empty($filters['status_id'])) {
            $query->where('lead_status_id', $filters['status_id']);
        }
        if (! empty($filters['company'])) {
            $query->where('company_name', 'like', '%'.$filters['company'].'%');
        }
        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
    }
}
