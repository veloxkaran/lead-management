<?php

namespace App\Support;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * @mentions in comments. There's no username field, so each person's
 * handle comes from their name: "Jane Doe" → @janedoe. When two active
 * people would share a handle, both get their id appended (@karan.6,
 * @karan.14) so a mention always points at exactly one person.
 *
 * Handles stay plain text in the saved comment, so a comment still reads
 * naturally anywhere it's shown (Slack, exports, emails). On screen,
 * highlight() turns known handles into name chips.
 */
class MentionParser
{
    private const PATTERN = '/(?<![\w@])@([a-zA-Z0-9._]+)/';

    /** @var array<string, User>|null handle => user, per request */
    private static ?array $handles = null;

    /**
     * Users mentioned in the text (each once, unknown handles ignored).
     *
     * @return Collection<int, User>
     */
    public static function extractUsers(string $text): Collection
    {
        preg_match_all(self::PATTERN, $text, $matches);

        $map = self::handleMap();

        return collect($matches[1])
            ->map(fn (string $handle) => $map[self::clean($handle)] ?? null)
            ->filter()
            ->unique('id')
            ->values();
    }

    public static function handle(User $user): string
    {
        foreach (self::handleMap() as $handle => $candidate) {
            if ($candidate->id === $user->id) {
                return $handle;
            }
        }

        return self::baseHandle($user);
    }

    /**
     * The text, HTML-escaped, with line breaks kept and every known
     * @handle shown as a chip with the person's name. The viewer's own
     * mentions get a stronger chip.
     */
    public static function highlight(?string $text, ?User $viewer = null): HtmlString
    {
        $map = self::handleMap();

        $html = preg_replace_callback(self::PATTERN, function (array $match) use ($map, $viewer) {
            $handle = self::clean($match[1]);
            $user = $map[$handle] ?? null;

            if (! $user) {
                return $match[0];
            }

            // Punctuation the regex swallowed ("@jane." at a sentence end) goes back after the chip.
            $trailing = substr($match[1], strlen($handle));
            $class = $viewer && $viewer->id === $user->id ? 'mention mention-me' : 'mention';

            return '<span class="'.$class.'" title="@'.$handle.'">@'.e($user->name).'</span>'.$trailing;
        }, e((string) $text));

        return new HtmlString(nl2br($html, false));
    }

    /**
     * Plain text with handles replaced by names — for Slack and notifications.
     */
    public static function toNames(string $text): string
    {
        $map = self::handleMap();

        return preg_replace_callback(self::PATTERN, function (array $match) use ($map) {
            $handle = self::clean($match[1]);

            return isset($map[$handle]) ? '@'.$map[$handle]->name.substr($match[1], strlen($handle)) : $match[0];
        }, $text);
    }

    /**
     * Everyone who can be mentioned, keyed by handle.
     *
     * @return array<string, User>
     */
    public static function handleMap(): array
    {
        if (self::$handles !== null) {
            return self::$handles;
        }

        $users = User::where('status', UserStatus::Active)->orderBy('id')->get();
        $groups = $users->groupBy(fn (User $user) => self::baseHandle($user));

        $map = [];
        foreach ($groups as $base => $group) {
            foreach ($group as $user) {
                $map[$group->count() > 1 ? "{$base}.{$user->id}" : $base] = $user;
            }
        }

        return self::$handles = $map;
    }

    /** Forget the cached handles (after users change, and between tests). */
    public static function flush(): void
    {
        self::$handles = null;
    }

    private static function baseHandle(User $user): string
    {
        return Str::slug($user->name, '') ?: 'user'.$user->id;
    }

    /** "@jane." / "@jane_" at the end of a sentence: drop the trailing punctuation. */
    private static function clean(string $handle): string
    {
        return Str::lower(rtrim($handle, '._'));
    }
}
