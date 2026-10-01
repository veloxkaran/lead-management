<?php

namespace App\Enums;

use App\Models\Agenda;
use App\Models\Announcement;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\DailySummary;
use App\Models\FollowUp;
use App\Models\Goal;
use App\Models\KnowledgeBaseItem;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\RawData;
use App\Models\ReleaseNote;
use App\Models\Requirement;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\Training;

/**
 * The components a Super Admin can grant/revoke per member (see
 * User::hasPermission()). Configuration screens (users, lead statuses,
 * settings, email templates) aren't listed — they're Super Admin only, and
 * Super Admins always have full access.
 */
enum PermissionModule: string
{
    case Leads = 'leads';
    case RawData = 'raw_data';
    case Requirements = 'requirements';
    case FollowUps = 'follow_ups';
    case Tasks = 'tasks';
    case SupportTickets = 'support_tickets';
    case Goals = 'goals';
    case Trainings = 'trainings';
    case KnowledgeBase = 'knowledge_base';
    case ReleaseNotes = 'release_notes';
    case Announcements = 'announcements';
    case Campaigns = 'campaigns';
    case Contacts = 'contacts';
    case Meetings = 'meetings';
    case MeetingRoom = 'meeting_room';
    case DailySummaries = 'daily_summaries';
    case Reports = 'reports';

    public function label(): string
    {
        return match ($this) {
            self::Leads => 'Lead Management',
            self::RawData => 'Raw Data',
            self::Requirements => 'Requirements',
            self::FollowUps => 'Follow Ups',
            self::Tasks => 'Tasks',
            self::SupportTickets => 'Support Tickets',
            self::Goals => 'Goals',
            self::Trainings => 'Trainings',
            self::KnowledgeBase => 'Knowledge Base',
            self::ReleaseNotes => 'Release Notes',
            self::Announcements => 'Announcements',
            self::Campaigns => 'Campaigns',
            self::Contacts => 'Contacts',
            self::Meetings => 'Meetings',
            self::MeetingRoom => 'Meeting Room',
            self::DailySummaries => 'Daily Summaries',
            self::Reports => 'Reports',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Leads => 'bi-diagram-3',
            self::RawData => 'bi-inbox',
            self::Requirements => 'bi-list-check',
            self::FollowUps => 'bi-bell',
            self::Tasks => 'bi-list-task',
            self::SupportTickets => 'bi-life-preserver',
            self::Goals => 'bi-bullseye',
            self::Trainings => 'bi-mortarboard',
            self::KnowledgeBase => 'bi-journal-richtext',
            self::ReleaseNotes => 'bi-megaphone',
            self::Announcements => 'bi-broadcast',
            self::Campaigns => 'bi-send',
            self::Contacts => 'bi-person-lines-fill',
            self::Meetings => 'bi-calendar-event',
            self::MeetingRoom => 'bi-people',
            self::DailySummaries => 'bi-journal-text',
            self::Reports => 'bi-bar-chart',
        };
    }

    /**
     * Only the actions the component actually has — announcements can't be
     * edited or deleted, reports are read-only, etc.
     *
     * @return list<PermissionAction>
     */
    public function actions(): array
    {
        return match ($this) {
            self::Announcements => [PermissionAction::View, PermissionAction::Create],
            // Sent campaigns can't be edited or deleted — "Edit" is cancelling one still pending.
            self::Campaigns => [PermissionAction::View, PermissionAction::Create, PermissionAction::Update],
            self::MeetingRoom, self::DailySummaries => [PermissionAction::View, PermissionAction::Create, PermissionAction::Update],
            self::Reports => [PermissionAction::View],
            default => PermissionAction::cases(),
        };
    }

    public function supports(PermissionAction $action): bool
    {
        return in_array($action, $this->actions(), true);
    }

    /**
     * The component a policy subject (model instance or class name) belongs
     * to, or null when it isn't permission-controlled.
     */
    public static function forSubject(mixed $subject): ?self
    {
        $class = is_object($subject) ? $subject::class : $subject;

        return match ($class) {
            Lead::class => self::Leads,
            RawData::class => self::RawData,
            Requirement::class => self::Requirements,
            FollowUp::class => self::FollowUps,
            Task::class => self::Tasks,
            SupportTicket::class => self::SupportTickets,
            Goal::class => self::Goals,
            Training::class => self::Trainings,
            KnowledgeBaseItem::class => self::KnowledgeBase,
            ReleaseNote::class => self::ReleaseNotes,
            Announcement::class => self::Announcements,
            Campaign::class => self::Campaigns,
            Contact::class => self::Contacts,
            Meeting::class => self::Meetings,
            Agenda::class => self::MeetingRoom,
            DailySummary::class => self::DailySummaries,
            default => null,
        };
    }
}
