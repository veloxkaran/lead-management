<?php

namespace App\Services;

use App\Enums\RequirementStatus;
use App\Models\ActivityLogEntry;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadNote;
use App\Models\SupportTicket;
use App\Models\SupportTicketStatusLog;
use App\Support\BsDate;
use App\Support\PublicUrl;
use App\Support\RecordUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * "What happened in the CRM today" — one row per thing someone did:
 * SN | company | what happened | who | when. Posted to Slack every day at
 * 4:20 PM (SendDailyActivitySummary).
 *
 * Sources: the Team Activities feed (new leads, status moves, deals,
 * requirements, follow-ups, meetings, tasks, WhatsApp…) plus what that feed
 * doesn't record — activities logged on a lead (calls, demos…), notes, and
 * support tickets raised or moved/resolved (with who did it, from the
 * ticket's status log).
 */
class DailyActivitySummary
{
    public const MAX_ROWS = 80;

    /**
     * @return Collection<int, array{company: ?string, lead_id: ?int, what: string, who: string, at: Carbon}>
     */
    public function rows(Carbon $day): Collection
    {
        [$from, $to] = [$day->copy()->startOfDay(), $day->copy()->endOfDay()];

        $rows = collect()
            ->concat($this->feedRows($from, $to))
            ->concat($this->leadActivityRows($from, $to))
            ->concat($this->noteRows($from, $to))
            ->concat($this->ticketRows($from, $to));

        // Company names in one query for everything that points at a lead.
        $companies = Lead::withTrashed()->whereIn('id', $rows->pluck('lead_id')->filter()->unique())->pluck('company_name', 'id');

        return $rows
            ->map(fn (array $row) => [...$row, 'company' => $row['company'] ?? ($row['lead_id'] ? $companies[$row['lead_id']] ?? null : null)])
            ->sortBy(fn (array $row) => $row['at']->getTimestamp())
            ->values();
    }

    /**
     * The Slack message: a bulleted, numbered list.
     */
    public function slackText(Carbon $day): string
    {
        $rows = $this->rows($day);
        $title = '*:clipboard: '.config('app.name').' — CRM summary for '.$day->format('D, M j, Y').' ('.BsDate::dayLabel($day).')*';

        if ($rows->isEmpty()) {
            return $title."\nNo activity was recorded in the CRM today.";
        }

        $linked = PublicUrl::isPublic();
        $companies = $rows->pluck('company')->filter()->unique()->count();
        $people = $rows->pluck('who')->unique()->count();

        $lines = [
            $title,
            "{$rows->count()} ".Str::plural('update', $rows->count())." · {$companies} ".Str::plural('company', $companies)." · {$people} ".Str::plural('person', $people),
            '',
            '_SN | Company | What happened | Who | When_',
        ];

        foreach ($rows->take(self::MAX_ROWS) as $index => $row) {
            $company = $row['company'] ? $this->escape(Str::limit($row['company'], 40)) : 'General';
            $url = $linked && $row['lead_id'] ? RecordUrl::forLead($row['lead_id']) : null;

            $lines[] = '• '.($index + 1).' | '
                .($url ? '*<'.$url.'|'.str_replace('|', '/', $company).'>*' : "*{$company}*")
                .' | '.$this->escape(Str::limit($this->oneLine($row['what']), 140))
                .' | '.$this->escape($row['who'])
                .' | '.$row['at']->format('g:i A');
        }

        if (($more = $rows->count() - self::MAX_ROWS) > 0) {
            $lines[] = "…and {$more} more"
                .($linked ? ' — <'.route('team.activities', ['date_from' => $day->toDateString(), 'date_to' => $day->toDateString()]).'|see Team Activities>' : '.');
        }

        return implode("\n", $lines);
    }

    private function feedRows(Carbon $from, Carbon $to): Collection
    {
        return ActivityLogEntry::with(['user:id,name', 'subject'])
            ->whereBetween('created_at', [$from, $to])
            // Notes come from noteRows() instead, which shows what the note says.
            ->where('subject_type', '!=', (new LeadNote)->getMorphClass())
            ->get()
            ->map(function (ActivityLogEntry $entry) {
                $subject = $entry->subject;

                return [
                    'company' => $subject instanceof Lead ? $subject->company_name : null,
                    'lead_id' => $subject instanceof Lead ? $subject->id : ($subject?->getAttribute('lead_id') ?: null),
                    'what' => $entry->description,
                    'who' => $entry->user?->name ?? 'System',
                    'at' => $entry->created_at,
                ];
            });
    }

    private function leadActivityRows(Carbon $from, Carbon $to): Collection
    {
        return LeadActivity::with('creator:id,name')
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->map(fn (LeadActivity $activity) => [
                'company' => null,
                'lead_id' => $activity->lead_id,
                'what' => 'logged a '.($activity->activity_type?->label() ?? 'activity').($activity->description ? ': '.$activity->description : ''),
                'who' => $activity->creator?->name ?? 'System',
                'at' => $activity->created_at,
            ]);
    }

    private function noteRows(Carbon $from, Carbon $to): Collection
    {
        return LeadNote::with('author:id,name')
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->map(fn (LeadNote $note) => [
                'company' => null,
                'lead_id' => $note->lead_id,
                'what' => 'added a note: '.$note->comment,
                'who' => $note->author?->name ?? 'System',
                'at' => $note->created_at,
            ]);
    }

    private function ticketRows(Carbon $from, Carbon $to): Collection
    {
        $raised = SupportTicket::with('raiser:id,name')
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->map(fn (SupportTicket $ticket) => [
                'company' => null,
                'lead_id' => $ticket->lead_id,
                'what' => 'raised a support ticket: '.$ticket->subject,
                'who' => $ticket->raiser?->name ?? ($ticket->is_client_submitted ? 'Client (support portal)' : 'System'),
                'at' => $ticket->created_at,
            ]);

        // Status changes, with who made them (resolving = moving to Completed).
        $statusChanges = SupportTicketStatusLog::with(['changedBy:id,name', 'supportTicket:id,lead_id,subject'])
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->filter(fn (SupportTicketStatusLog $log) => $log->supportTicket)
            ->map(fn (SupportTicketStatusLog $log) => [
                'company' => null,
                'lead_id' => $log->supportTicket->lead_id,
                'what' => match ($log->to_status) {
                    RequirementStatus::Completed => 'resolved a support ticket: '.$log->supportTicket->subject,
                    RequirementStatus::Cancelled => 'cancelled a support ticket: '.$log->supportTicket->subject,
                    default => 'moved support ticket "'.$log->supportTicket->subject.'" to '.($log->to_status?->label() ?? 'a new status'),
                },
                'who' => $log->changedBy?->name ?? 'System',
                'at' => $log->created_at,
            ]);

        return $raised->concat($statusChanges);
    }

    private function oneLine(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES)));
    }

    /** Slack mrkdwn treats &, < and > as control characters. */
    private function escape(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }
}
