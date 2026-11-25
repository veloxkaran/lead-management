<?php

namespace App\Support;

/**
 * Builds the links that go inside outgoing emails — the open-tracking
 * image and the unsubscribe link.
 *
 * Gmail judges an email partly by the domains it links to: a link to a
 * different domain than the sender's, over plain http, or to a domain with
 * a poor reputation pushes it toward spam or gets it refused. So:
 *
 *  - links are built from MAIL_LINKS_URL when set (e.g.
 *    https://track.hajirhr.com, a host on the sender's own domain that
 *    serves this app), falling back to APP_URL;
 *  - the tracking image is only ever added over HTTPS on a public host —
 *    otherwise it's left out entirely, which costs nothing but open stats.
 */
class EmailLinks
{
    public static function base(): string
    {
        return rtrim((string) (config('mail.links_url') ?: config('app.url')), '/');
    }

    /**
     * Absolute URL for a named route, on the email-links host.
     */
    public static function route(string $name, mixed $parameters = []): string
    {
        return self::base().route($name, $parameters, false);
    }

    /**
     * The 1×1 open-tracking image URL, or null when it shouldn't be added.
     */
    public static function trackingPixel(string $name, string $token): ?string
    {
        return self::canTrack() ? self::route($name, $token) : null;
    }

    /**
     * Whether a tracking image would be served from a public HTTPS host.
     */
    public static function canTrack(): bool
    {
        $base = self::base();

        return parse_url($base, PHP_URL_SCHEME) === 'https' && PublicUrl::isPublic($base);
    }
}
