<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\Campaign;
use App\Models\EmailLog;
use App\Models\Goal;
use App\Models\KnowledgeBaseItem;
use App\Models\Lead;
use App\Models\RawData;
use App\Models\ReleaseNote;
use App\Models\Requirement;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * The app page that shows a record — for links in Slack posts. The pages
 * themselves still require login and run their usual permission checks, so
 * a link only opens for someone allowed to see that record.
 *
 * A record without its own page (a note, a follow-up, a comment…) links to
 * the record it belongs to, found through its foreign key — no queries.
 */
class RecordUrl
{
    /** Models with their own page => that page's route name. */
    private const SHOW_ROUTES = [
        Lead::class => 'leads.show',
        RawData::class => 'raw-data.show',
        Requirement::class => 'requirements.show',
        SupportTicket::class => 'support-tickets.show',
        Task::class => 'tasks.show',
        Campaign::class => 'campaigns.show',
        Announcement::class => 'announcements.show',
        KnowledgeBaseItem::class => 'knowledge-base.show',
        ReleaseNote::class => 'release-notes.show',
        Goal::class => 'goals.show',
        User::class => 'users.show',
        EmailLog::class => 'email-logs.show',
    ];

    /** Foreign key => the parent model it points at, most specific first. */
    private const PARENT_KEYS = [
        'support_ticket_id' => SupportTicket::class,
        'requirement_id' => Requirement::class,
        'task_id' => Task::class,
        'campaign_id' => Campaign::class,
        'announcement_id' => Announcement::class,
        'release_note_id' => ReleaseNote::class,
        'raw_data_id' => RawData::class,
        'lead_id' => Lead::class,
    ];

    /**
     * @param  bool  $exists  false for a deleted record — only its parent can be linked
     */
    public static function forModel(Model $model, bool $exists = true): ?string
    {
        if ($exists && $url = self::route(self::SHOW_ROUTES[$model::class] ?? null, $model->getKey())) {
            return $url;
        }

        $attributes = $model->getAttributes();

        foreach (self::PARENT_KEYS as $key => $parent) {
            if (! empty($attributes[$key]) && $url = self::route(self::SHOW_ROUTES[$parent], $attributes[$key])) {
                return $url;
            }
        }

        return null;
    }

    public static function forLead(?int $leadId): ?string
    {
        return $leadId ? self::route('leads.show', $leadId) : null;
    }

    /**
     * The nearest page someone can open for a request path: the path itself
     * if it has a GET page (PUT /leads/12 → /leads/12), else its closest
     * parent that does (POST /leads/12/notes → /leads/12).
     */
    public static function pageForPath(string $path): ?string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));

        while ($segments) {
            $candidate = '/'.implode('/', $segments);

            try {
                $route = Route::getRoutes()->match(Request::create($candidate, 'GET'));

                // Skip catch-all/redirect-only routes without a name.
                if ($route->getName()) {
                    return url($candidate);
                }
            } catch (Throwable) {
                // No GET page here — try the parent path.
            }

            array_pop($segments);
        }

        return null;
    }

    private static function route(?string $name, mixed $key): ?string
    {
        if (! $name || $key === null || ! Route::has($name)) {
            return null;
        }

        try {
            return route($name, $key);
        } catch (Throwable) {
            return null;
        }
    }
}
