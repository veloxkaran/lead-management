<?php

namespace App\Support\ActivityModules;

use App\Enums\ActivityModule;
use App\Models\Agenda;
use App\Models\DealClosure;
use App\Models\EmailAccount;
use App\Models\FollowUp;
use App\Models\Goal;
use App\Models\KnowledgeBaseItem;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadStatusHistory;
use App\Models\Meeting;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The single source of truth for what the Activity Feed knows about each
 * module: its label/icon, how a viewer's permission + click-through link is
 * resolved, and which model-creation events log an entry for it. Everywhere
 * else in the app (ActivityLinkResolver, AppServiceProvider, the Super Admin
 * settings checkboxes, ActivityModule::label()/icon()) reads from this
 * registry instead of hardcoding module-specific logic — extending the feed
 * to a 9th module means adding one enum case plus one entry in each array
 * below, not modifying any of those consumers.
 */
class ActivityModuleRegistry
{
    /** @var array<string, ActivityModuleDefinition>|null */
    private static ?array $definitions = null;

    /** @var array<int, ActivityLoggingRegistration>|null */
    private static ?array $loggingRegistrations = null;

    /**
     * @return array<string, ActivityModuleDefinition> keyed by module value
     */
    public static function definitions(): array
    {
        return self::$definitions ??= collect(self::buildDefinitions())
            ->keyBy(fn (ActivityModuleDefinition $definition) => $definition->module->value)
            ->all();
    }

    public static function definition(ActivityModule $module): ActivityModuleDefinition
    {
        return self::definitions()[$module->value];
    }

    /**
     * @return array<int, string> every registered module's value, in display order
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return array<int, ActivityLoggingRegistration>
     */
    public static function loggingRegistrations(): array
    {
        return self::$loggingRegistrations ??= self::buildLoggingRegistrations();
    }

    /**
     * @return array<int, ActivityModuleDefinition>
     */
    private static function buildDefinitions(): array
    {
        return [
            new ActivityModuleDefinition(
                ActivityModule::Lead, 'Leads', 'bi-diagram-3',
                function (Model $subject, User $viewer): array {
                    $lead = $subject instanceof Lead ? $subject : $subject->lead;

                    return self::linkFor($lead, $viewer, 'view', fn (Lead $lead) => route('leads.show', $lead));
                },
            ),
            new ActivityModuleDefinition(
                ActivityModule::Requirement, 'Requirements', 'bi-list-check',
                fn (Model $subject, User $viewer) => self::linkFor($subject, $viewer, 'update', fn ($r) => route('requirements.edit', $r)),
            ),
            new ActivityModuleDefinition(
                ActivityModule::FollowUp, 'Follow Ups', 'bi-bell',
                fn (Model $subject, User $viewer) => self::linkFor($subject, $viewer, 'update', fn ($f) => route('follow-ups.edit', $f)),
            ),
            new ActivityModuleDefinition(
                ActivityModule::Whatsapp, 'WhatsApp', 'bi-whatsapp',
                function (Model $subject, User $viewer): array {
                    return self::linkFor($subject->lead, $viewer, 'chatWhatsapp', fn (Lead $lead) => route('whatsapp.show', $lead));
                },
            ),
            new ActivityModuleDefinition(
                ActivityModule::Goal, 'Goals', 'bi-bullseye',
                fn (Model $subject, User $viewer) => self::linkFor($subject, $viewer, 'update', fn ($g) => route('goals.edit', $g)),
            ),
            new ActivityModuleDefinition(
                ActivityModule::KnowledgeBase, 'Knowledge Base', 'bi-journal-richtext',
                fn (Model $subject, User $viewer) => self::linkFor($subject, $viewer, 'view', fn ($k) => route('knowledge-base.show', $k)),
            ),
            new ActivityModuleDefinition(
                ActivityModule::Meeting, 'Meetings', 'bi-camera-video',
                fn (Model $subject, User $viewer) => self::linkFor($subject, $viewer, 'update', fn ($m) => route('meetings.edit', $m)),
            ),
            new ActivityModuleDefinition(
                ActivityModule::Task, 'Tasks', 'bi-list-task',
                function (Model $subject, User $viewer): array {
                    $task = $subject instanceof Task ? $subject : $subject->task;

                    return self::linkFor($task, $viewer, 'view', fn (Task $task) => route('tasks.show', $task));
                },
            ),
            new ActivityModuleDefinition(
                ActivityModule::Email, 'Email Accounts', 'bi-envelope-at',
                fn (Model $subject, User $viewer) => self::linkFor($subject, $viewer, 'view', fn ($a) => route('email-accounts.edit', $a)),
            ),
            new ActivityModuleDefinition(
                ActivityModule::Note, 'Notes', 'bi-sticky',
                function (Model $subject, User $viewer): array {
                    return self::linkFor($subject->lead, $viewer, 'view', fn (Lead $lead) => route('leads.show', $lead));
                },
            ),
            new ActivityModuleDefinition(
                ActivityModule::Agenda, 'Team Meeting Room', 'bi-people',
                fn (Model $subject, User $viewer) => self::linkFor($subject, $viewer, 'view', fn ($a) => route('meeting-room.index', ['agenda' => $a->id])),
            ),
        ];
    }

    /**
     * @return array<int, ActivityLoggingRegistration>
     */
    private static function buildLoggingRegistrations(): array
    {
        return [
            new ActivityLoggingRegistration(
                Lead::class, ActivityModule::Lead,
                fn (Lead $lead) => "created a new lead: {$lead->company_name}",
                fn (Lead $lead) => $lead->created_by,
            ),
            new ActivityLoggingRegistration(
                LeadStatusHistory::class, ActivityModule::Lead,
                fn (LeadStatusHistory $history) => "moved {$history->lead->company_name} to {$history->toStatus->name}",
                fn (LeadStatusHistory $history) => $history->changed_by,
            ),
            new ActivityLoggingRegistration(
                DealClosure::class, ActivityModule::Lead,
                fn (DealClosure $deal) => "closed a deal: {$deal->lead->company_name} (".Currency::format($deal->deal_value).')',
                fn (DealClosure $deal) => $deal->closed_by,
            ),
            new ActivityLoggingRegistration(
                Requirement::class, ActivityModule::Requirement,
                fn (Requirement $requirement) => 'raised a requirement: '.Str::limit($requirement->requirement, 60),
                fn (Requirement $requirement) => $requirement->created_by,
            ),
            new ActivityLoggingRegistration(
                FollowUp::class, ActivityModule::FollowUp,
                fn (FollowUp $followUp) => "scheduled a follow-up for {$followUp->lead->company_name} on {$followUp->follow_up_date->format('M d, Y')}",
                fn (FollowUp $followUp) => $followUp->created_by,
            ),
            new ActivityLoggingRegistration(
                WhatsappMessage::class, ActivityModule::Whatsapp,
                fn (WhatsappMessage $message) => "sent a WhatsApp message to {$message->lead->company_name}",
                fn (WhatsappMessage $message) => $message->sent_by,
            ),
            new ActivityLoggingRegistration(
                Goal::class, ActivityModule::Goal,
                fn (Goal $goal) => "set a new goal: {$goal->title}",
                fn (Goal $goal) => $goal->created_by,
            ),
            new ActivityLoggingRegistration(
                KnowledgeBaseItem::class, ActivityModule::KnowledgeBase,
                fn (KnowledgeBaseItem $item) => "added to knowledge base: {$item->title}",
                fn (KnowledgeBaseItem $item) => $item->uploaded_by,
            ),
            new ActivityLoggingRegistration(
                Meeting::class, ActivityModule::Meeting,
                fn (Meeting $meeting) => "scheduled a meeting: {$meeting->title}",
                fn (Meeting $meeting) => $meeting->created_by,
            ),
            new ActivityLoggingRegistration(
                Task::class, ActivityModule::Task,
                fn (Task $task) => "created a new task: {$task->title}",
                fn (Task $task) => $task->created_by,
            ),
            new ActivityLoggingRegistration(
                EmailAccount::class, ActivityModule::Email,
                fn (EmailAccount $account) => "connected an email account: {$account->email_address}",
                fn (EmailAccount $account) => $account->user_id,
            ),
            new ActivityLoggingRegistration(
                LeadNote::class, ActivityModule::Note,
                fn (LeadNote $note) => "added a note on {$note->lead->company_name}",
                fn (LeadNote $note) => $note->author_id,
            ),
            new ActivityLoggingRegistration(
                Agenda::class, ActivityModule::Agenda,
                fn (Agenda $agenda) => "raised a team meeting agenda: {$agenda->title}",
                fn (Agenda $agenda) => $agenda->created_by,
            ),
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel|null  $target
     * @param  callable(TModel): string  $routeBuilder
     * @return array{can_view: bool, url: string|null}
     */
    private static function linkFor(?Model $target, User $viewer, string $ability, callable $routeBuilder): array
    {
        if (! $target) {
            return ['can_view' => false, 'url' => null];
        }

        $canView = Gate::forUser($viewer)->allows($ability, $target);

        return ['can_view' => $canView, 'url' => $canView ? $routeBuilder($target) : null];
    }
}
