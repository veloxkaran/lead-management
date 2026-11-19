<?php

namespace App\Services;

use App\Jobs\PostSlackWriteActivity;
use App\Models\Agenda;
use App\Models\Lead;
use App\Models\RawData;
use App\Models\Requirement;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\User;
use App\Notifications\MentionedNotification;
use App\Support\MentionParser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Handles the @mentions in a newly posted (or edited) comment:
 *
 *   • only people who are active and allowed to see the record are notified
 *     (the same rule decides who the composer suggests — see mentionable())
 *   • each gets a bell notification linking to the record
 *   • one Slack post names everyone mentioned, on the write-activity webhook
 *
 * Editing a comment only notifies people newly added in the edit.
 */
class MentionNotifier
{
    /** Comment subjects people can be mentioned on, keyed as the composer refers to them. */
    public const SUBJECTS = [
        'lead' => Lead::class,
        'task' => Task::class,
        'requirement' => Requirement::class,
        'support_ticket' => SupportTicket::class,
        'raw_data' => RawData::class,
        'agenda' => Agenda::class,
    ];

    /**
     * @return Collection<int, User> who was notified
     *
     * @param  bool  $inApp  false when the caller already sends its own bell
     *                       notification (Meeting Room agendas) — Slack only
     */
    public function notify(string $text, User $author, Model $subject, ?string $previousText = null, bool $inApp = true): Collection
    {
        $mentioned = MentionParser::extractUsers($text);

        if ($previousText !== null) {
            $before = MentionParser::extractUsers($previousText)->pluck('id');
            $mentioned = $mentioned->reject(fn (User $user) => $before->contains($user->id));
        }

        $recipients = $mentioned
            ->reject(fn (User $user) => $user->id === $author->id)
            ->filter(fn (User $user) => $this->canSee($user, $subject))
            ->values();

        if ($recipients->isEmpty()) {
            return $recipients;
        }

        [$where, $url] = $this->describe($subject);
        $excerpt = Str::limit(trim(preg_replace('/\s+/', ' ', MentionParser::toNames($text))), 140);

        if ($inApp) {
            Notification::send($recipients, new MentionedNotification($author->name, $where, $url, $excerpt));
        }

        $this->postToSlack($author, $recipients, $where, $url, $excerpt);

        return $recipients;
    }

    /**
     * Who the composer should suggest for this record: active teammates
     * (not the author) who are allowed to see it.
     *
     * @return Collection<int, array{id: int, name: string, handle: string, role: ?string}>
     */
    public function mentionable(Model $subject, User $author): Collection
    {
        return collect(MentionParser::handleMap())
            ->reject(fn (User $user) => $user->id === $author->id)
            ->filter(fn (User $user) => $this->canSee($user, $subject))
            ->map(fn (User $user, string $handle) => [
                'id' => $user->id,
                'name' => $user->name,
                'handle' => $handle,
                'role' => $user->role?->label(),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function canSee(User $user, Model $subject): bool
    {
        return $user->isActive() && Gate::forUser($user)->allows('view', $subject);
    }

    /**
     * @return array{0: string, 1: string} "lead “Acme Traders”", link to it
     */
    private function describe(Model $subject): array
    {
        return match (true) {
            $subject instanceof Lead => ['lead “'.$subject->company_name.'”', route('leads.show', $subject)],
            $subject instanceof Task => ['task “'.$subject->title.'”', route('tasks.show', $subject)],
            $subject instanceof Requirement => ['a requirement for “'.($subject->lead?->company_name ?? 'a client').'”', route('requirements.show', $subject)],
            $subject instanceof SupportTicket => ['support ticket “'.$subject->subject.'”', route('support-tickets.show', $subject)],
            $subject instanceof RawData => ['raw data “'.($subject->company_name ?: $subject->contact_person ?: '#'.$subject->id).'”', route('raw-data.show', $subject)],
            $subject instanceof Agenda => ['agenda “'.$subject->title.'”', route('meeting-room.index')],
            default => ['a record', route('dashboard')],
        };
    }

    /**
     * ":speech_balloon: Karan mentioned Jane Doe, Bikash KC on <lead “Acme”>"
     * plus the comment as a quote. Sent after the response so posting never
     * slows the page; a failed post is retried from the queue.
     */
    private function postToSlack(User $author, Collection $recipients, string $where, string $url, string $excerpt): void
    {
        if (blank(config('services.slack.activity_webhook_url'))) {
            return;
        }

        $escape = fn (string $value) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
        $names = $recipients->map(fn (User $user) => '*'.$escape($user->name).'*')->implode(', ');

        $text = ':speech_balloon: *'.$escape($author->name).'* mentioned '.$names
            .' on <'.$url.'|'.str_replace('|', '/', $escape($where)).'>'
            ."\n> ".$escape($excerpt);

        // Once only, even if the app's terminate step runs more than once.
        $posted = false;
        app()->terminating(function () use ($text, &$posted) {
            if ($posted) {
                return;
            }
            $posted = true;

            if (! PostSlackWriteActivity::deliver($text)) {
                PostSlackWriteActivity::dispatch($text);
            }
        });
    }
}
