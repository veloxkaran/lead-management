<?php

namespace App\Settings;

use App\Models\Setting;
use App\Support\ActivityModules\ActivityModuleRegistry;

/**
 * Single source of truth for the Activity Feed's config keys — see
 * WhatsappSettings for why this pattern exists (previously
 * `Setting::get('activity_feed_...')` string literals were duplicated across
 * the controller, the settings controller, and the Blade widget itself).
 */
class ActivityFeedSettings
{
    public const KEYS = [
        'activity_feed_enabled',
        'activity_feed_refresh_seconds',
        'activity_feed_per_page',
        'activity_feed_enabled_modules',
    ];

    /**
     * An unset key means "on" — the widget shipped enabled by default, so
     * absence of a setting row must mean enabled, not disabled.
     */
    public function enabled(): bool
    {
        return Setting::get('activity_feed_enabled', '1') !== '0';
    }

    public function refreshSeconds(): int
    {
        return (int) (Setting::get('activity_feed_refresh_seconds') ?: 10);
    }

    public function perPage(): int
    {
        return max(1, (int) (Setting::get('activity_feed_per_page') ?: 10));
    }

    /**
     * @return array<int, string>
     */
    public function enabledModules(): array
    {
        $configured = Setting::get('activity_feed_enabled_modules');

        if (blank($configured)) {
            return ActivityModuleRegistry::keys();
        }

        return array_values(array_filter(explode(',', $configured)));
    }

    /**
     * @return array<string, string|null> raw values for the edit form —
     *                                     `activity_feed_enabled` is
     *                                     normalized to match enabled()'s
     *                                     default so the checkbox never
     *                                     shows "off" for a widget that's
     *                                     actually rendering right now.
     */
    public function raw(): array
    {
        $values = collect(self::KEYS)->mapWithKeys(fn (string $key) => [$key => Setting::get($key)])->all();
        $values['activity_feed_enabled'] ??= '1';

        return $values;
    }

    /**
     * @param  array<int, string>  $modules
     */
    public function save(int $refreshSeconds, int $perPage, array $modules, bool $enabled): void
    {
        Setting::set('activity_feed_refresh_seconds', (string) $refreshSeconds);
        Setting::set('activity_feed_per_page', (string) $perPage);
        Setting::set('activity_feed_enabled_modules', implode(',', $modules));
        Setting::set('activity_feed_enabled', $enabled ? '1' : '0');
    }
}
