<?php

namespace App\Support;

/**
 * Whether APP_URL is reachable from the internet. Links in an email
 * (tracking image, unsubscribe) are built from it; "http://localhost" or a
 * private IP there means broken links for every recipient — and links to
 * localhost/private addresses are a classic spam-filter signal.
 */
class PublicUrl
{
    public static function isPublic(?string $url = null): bool
    {
        $url ??= (string) config('app.url');
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '' || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return false;
        }

        if ($host === 'localhost' || preg_match('/\.(localhost|local|test|invalid|example|internal|lan)$/', $host)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return str_contains($host, '.');
    }
}
