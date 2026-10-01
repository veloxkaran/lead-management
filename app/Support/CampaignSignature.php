<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mews\Purifier\Facades\Purifier;
use Throwable;

/**
 * The campaign email signature: rich-text HTML plus images, 50 KB at most
 * in total.
 *
 * The editor inserts images as base64 data URIs (toolbar button, paste or
 * drop). Mail apps — Gmail above all — won't show data-URI images, so on
 * save each one is decoded, checked, stored on the public disk under
 * campaign-signature/, and its src swapped for the stored file's
 * root-relative path (/storage/…) — not an absolute APP_URL link, so the
 * editor shows it on whatever host the app is opened at. When
 * a campaign email is sent the images are embedded inline (cid:), so they
 * show without the recipient clicking "load images".
 *
 * Only images stored this way are allowed: an image linked from another
 * site can't be size-checked and is blocked by most mail apps.
 */
class CampaignSignature
{
    public const MAX_BYTES = 50 * 1024;

    public const DIRECTORY = 'campaign-signature';

    /** Wider than this (px) gets scaled down when an image alone would blow the budget. */
    private const SHRINK_WIDTH = 400;

    private const MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];

    private const TYPES = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];

    /** Matches a stored signature image in any src, whatever APP_URL was when it was saved. */
    private const STORED = '#/storage/'.self::DIRECTORY.'/([a-f0-9]{40}\.(?:png|jpg|gif|webp))$#';

    /**
     * Turns editor HTML into the signature to save: images stored, markup
     * sanitized, total size checked. Deletes stored images the new
     * signature no longer uses. A stored image whose file has gone missing
     * is dropped from the signature (counted in $missing) rather than
     * blocking the save.
     *
     * @throws ValidationException
     */
    public static function save(?string $html, string $field = 'campaign_signature', ?int &$missing = null): ?string
    {
        $missing = 0;
        $html = trim((string) $html);

        if ($html === '' || trim(HtmlToText::convert($html)) === '' && ! str_contains($html, '<img')) {
            self::deleteUnused([]);

            return null;
        }

        $html = preg_replace_callback(
            '#(<img\b[^>]*?\bsrc\s*=\s*)(["\'])data:image/[a-z+.-]+;base64,([A-Za-z0-9+/=\s]+)\2#i',
            fn (array $m) => $m[1].$m[2].self::storeDataUri($m[3], $field).$m[2],
            $html,
        );

        $html = self::normalizeImages(Purifier::clean($html));

        $files = [];
        foreach (self::imageSources($html) as $src) {
            if (! preg_match(self::STORED, (string) parse_url($src, PHP_URL_PATH), $m)) {
                throw ValidationException::withMessages([$field => 'Images in the signature must be inserted or pasted into the editor, not linked from another site.']);
            }
            $files[] = $m[1];
        }

        if ($gone = self::missingImages($html)) {
            $missing = count($gone);
            $html = preg_replace_callback('#<img\b[^>]*>#i', fn (array $tag) => collect($gone)->contains(fn ($file) => str_contains($tag[0], $file)) ? '' : $tag[0], $html);
            $files = array_values(array_diff($files, $gone));
        }

        $bytes = self::bytes($html);
        if ($bytes > self::MAX_BYTES) {
            throw ValidationException::withMessages([$field => sprintf(
                'The signature is %s — the limit is 50 KB including images. Use a smaller logo (one around 200px wide is usually under 15 KB) or fewer images.',
                self::format($bytes),
            )]);
        }

        self::deleteUnused($files);

        return $html;
    }

    /**
     * HTML length plus every stored image it shows (each counted once).
     */
    public static function bytes(?string $html): int
    {
        $html = (string) $html;
        $bytes = strlen($html);

        foreach (self::imageSizes($html) as $size) {
            $bytes += $size;
        }

        return $bytes;
    }

    /**
     * Stored images the signature shows whose files no longer exist.
     *
     * @return array<int, string>
     */
    public static function missingImages(?string $html): array
    {
        return array_values(array_filter(
            array_unique(self::storedFiles((string) $html)),
            fn (string $file) => ! Storage::disk('public')->exists(self::DIRECTORY.'/'.$file),
        ));
    }

    /**
     * Stored image file => size in bytes, for the editor's live counter.
     * Missing files are left out (see missingImages()).
     *
     * @return array<string, int>
     */
    public static function imageSizes(?string $html): array
    {
        $disk = Storage::disk('public');
        $sizes = [];

        foreach (array_unique(self::storedFiles((string) $html)) as $file) {
            if ($disk->exists(self::DIRECTORY.'/'.$file)) {
                $sizes[$file] = (int) $disk->size(self::DIRECTORY.'/'.$file);
            }
        }

        return $sizes;
    }

    /**
     * The signature as it goes into an email. While actually mailing
     * ($message is the Symfony message wrapper) stored images are
     * embedded inline as cid: attachments; when only rendering (preview,
     * tests) they point at the current public URL. Every image gets a
     * width attribute — Outlook for Windows ignores CSS max-width.
     */
    public static function forEmail(?string $html, mixed $message = null): string
    {
        return preg_replace_callback('#<img\b[^>]*>#i', function (array $tag) use ($message) {
            if (! preg_match('#\bsrc\s*=\s*(["\'])(.*?)\1#i', $tag[0], $src)
                || ! preg_match(self::STORED, (string) parse_url(html_entity_decode($src[2]), PHP_URL_PATH), $m)) {
                return '';
            }

            $path = Storage::disk('public')->path(self::DIRECTORY.'/'.$m[1]);

            if (! is_file($path)) {
                return '';
            }

            // Embedded with its real type — a generic octet-stream part isn't shown as an image by every mail app.
            $url = $message
                ? $message->embedData((string) file_get_contents($path), $m[1], self::MIME[pathinfo($m[1], PATHINFO_EXTENSION)] ?? 'image/png')
                : self::publicPath($m[1]);
            $alt = self::cleanAlt($tag[0]);
            $width = EmailImage::displayWidth($path);

            return '<img src="'.e($url).'" width="'.$width.'" alt="'.$alt.'" style="max-width: 100%; height: auto; border: 0; display: inline-block;">';
        }, (string) $html);
    }

    /**
     * The saved signature as the editor should load it. Signatures saved
     * before paths were made relative hold absolute APP_URL links
     * (http://localhost/storage/…), which show as a broken image when the
     * app is opened at another address.
     */
    public static function forEditor(?string $html): ?string
    {
        return $html === null ? null : self::normalizeImages($html);
    }

    public static function format(int $bytes): string
    {
        return $bytes < 1024 ? "{$bytes} bytes" : number_format($bytes / 1024, 1).' KB';
    }

    /**
     * Rewrites every stored signature image to its root-relative path and
     * drops the alt text HTMLPurifier invents from the file name (a hash
     * that would show wherever images are blocked).
     */
    private static function normalizeImages(string $html): string
    {
        return preg_replace_callback('#<img\b[^>]*>#i', function (array $tag) {
            if (! preg_match('#\bsrc\s*=\s*(["\'])(.*?)\1#i', $tag[0], $src)
                || ! preg_match(self::STORED, (string) parse_url(html_entity_decode($src[2]), PHP_URL_PATH), $m)) {
                return $tag[0];
            }

            $alt = self::cleanAlt($tag[0]);

            return '<img src="'.e(self::publicPath($m[1])).'" alt="'.$alt.'">';
        }, $html);
    }

    /**
     * The tag's alt text, escaped — empty when it's only the file name.
     */
    private static function cleanAlt(string $tag): string
    {
        $alt = preg_match('#\balt\s*=\s*(["\'])(.*?)\1#i', $tag, $a) ? html_entity_decode($a[2]) : '';

        return preg_match('#^[a-f0-9]{40}\.(?:png|jpg|gif|webp)$#i', $alt) ? '' : e($alt);
    }

    /**
     * /storage/campaign-signature/<file> — the path part of the public
     * disk's URL, whatever APP_URL is.
     */
    private static function publicPath(string $file): string
    {
        return (string) parse_url(Storage::disk('public')->url(self::DIRECTORY.'/'.$file), PHP_URL_PATH);
    }

    private static function storeDataUri(string $base64, string $field): string
    {
        $contents = base64_decode(preg_replace('/\s+/', '', $base64), true);
        $info = $contents !== false ? @getimagesizefromstring($contents) : false;
        $extension = $info ? (self::TYPES[$info[2]] ?? null) : null;

        if (! $extension) {
            throw ValidationException::withMessages([$field => 'One of the signature images isn\'t a PNG, JPG, GIF or WebP image.']);
        }

        if (strlen($contents) > self::MAX_BYTES) {
            $contents = self::shrink($contents, $info) ?? $contents;
            $extension = self::TYPES[@getimagesizefromstring($contents)[2] ?? $info[2]] ?? $extension;
        }

        if (strlen($contents) > self::MAX_BYTES) {
            throw ValidationException::withMessages([$field => sprintf(
                'A signature image is %s — the whole signature can be at most 50 KB. Use a smaller image (a logo around 200px wide is usually under 15 KB).',
                self::format(strlen($contents)),
            )]);
        }

        $file = sha1($contents).'.'.$extension;
        Storage::disk('public')->put(self::DIRECTORY.'/'.$file, $contents);

        return self::publicPath($file);
    }

    /**
     * Scales a too-big PNG/JPEG down to SHRINK_WIDTH (a typical pasted
     * screenshot or full-size logo), so it can still fit. GIF/WebP and
     * hosts without GD are left as they are.
     */
    private static function shrink(string $contents, array $info): ?string
    {
        [$width, $height, $type] = $info;

        if (! extension_loaded('gd') || ! in_array($type, [IMAGETYPE_PNG, IMAGETYPE_JPEG], true) || $width <= self::SHRINK_WIDTH) {
            return null;
        }

        try {
            $source = imagecreatefromstring($contents);
            if (! $source) {
                return null;
            }

            $newHeight = max(1, (int) round($height * self::SHRINK_WIDTH / $width));
            $scaled = imagecreatetruecolor(self::SHRINK_WIDTH, $newHeight);

            if ($type === IMAGETYPE_PNG) {
                imagealphablending($scaled, false);
                imagesavealpha($scaled, true);
                imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
            }

            imagecopyresampled($scaled, $source, 0, 0, 0, 0, self::SHRINK_WIDTH, $newHeight, $width, $height);

            ob_start();
            $type === IMAGETYPE_PNG ? imagepng($scaled, null, 9) : imagejpeg($scaled, null, 82);
            $result = (string) ob_get_clean();


            return $result !== '' ? $result : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    private static function imageSources(string $html): array
    {
        preg_match_all('#<img\b[^>]*?\bsrc\s*=\s*(["\'])(.*?)\1#i', $html, $matches);

        return array_map('html_entity_decode', $matches[2]);
    }

    /**
     * @return array<int, string>
     */
    private static function storedFiles(string $html): array
    {
        $files = [];

        foreach (self::imageSources($html) as $src) {
            if (preg_match(self::STORED, (string) parse_url($src, PHP_URL_PATH), $m)) {
                $files[] = $m[1];
            }
        }

        return $files;
    }

    /**
     * @param  array<int, string>  $keep
     */
    private static function deleteUnused(array $keep): void
    {
        $disk = Storage::disk('public');

        foreach ($disk->files(self::DIRECTORY) as $path) {
            if (! in_array(basename($path), $keep, true)) {
                $disk->delete($path);
            }
        }
    }
}
