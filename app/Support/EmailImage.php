<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Prepares images for inline use in HTML email.
 *
 * - JPEG/PNG wider than MAX_STORED_WIDTH are scaled down (aspect kept) —
 *   2× the display width, so they stay sharp on retina screens without
 *   shipping a multi-MB phone photo to every recipient.
 * - JPEGs are always re-encoded: applies the EXIF rotation (otherwise
 *   phone photos can arrive sideways) and strips metadata such as GPS.
 * - GIFs are left untouched — re-encoding through GD would drop animation.
 *
 * Falls back to the original bytes whenever GD is missing or processing
 * fails, so an upload is never lost just because it couldn't be optimized.
 */
class EmailImage
{
    /** Content width of the 600px email layout (minus 32px padding each side). */
    public const MAX_DISPLAY_WIDTH = 536;

    public const MAX_STORED_WIDTH = self::MAX_DISPLAY_WIDTH * 2;

    private const JPEG_QUALITY = 82;

    /**
     * @return array{contents: string, extension: string}
     */
    public static function prepare(UploadedFile $file): array
    {
        $original = [
            'contents' => (string) file_get_contents($file->getRealPath()),
            'extension' => strtolower($file->guessExtension() ?: $file->getClientOriginalExtension()),
        ];

        if (! extension_loaded('gd')) {
            return $original;
        }

        try {
            return self::process($file->getRealPath()) ?? $original;
        } catch (Throwable $e) {
            report($e);

            return $original;
        }
    }

    /**
     * Width to put on the <img width=""> attribute — Outlook for Windows
     * ignores CSS max-width and only honours this. Never upscales.
     */
    public static function displayWidth(string $path): int
    {
        $width = @getimagesize($path)[0] ?? 0;

        return $width > 0 ? min($width, self::MAX_DISPLAY_WIDTH) : self::MAX_DISPLAY_WIDTH;
    }

    /**
     * @return array{contents: string, extension: string}|null  null = keep the original
     */
    private static function process(string $path): ?array
    {
        [$width, $height, $type] = getimagesize($path) ?: [0, 0, 0];

        if (! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true) || $width < 1 || $height < 1) {
            return null;
        }

        $orientation = $type === IMAGETYPE_JPEG ? self::exifOrientation($path) : 1;

        if ($type === IMAGETYPE_PNG && $width <= self::MAX_STORED_WIDTH) {
            return null;
        }

        // Decoding needs ~5 bytes per pixel; give large photos the headroom.
        $previousLimit = ini_get('memory_limit');
        @ini_set('memory_limit', '512M');

        try {
            $image = $type === IMAGETYPE_JPEG ? imagecreatefromjpeg($path) : imagecreatefrompng($path);

            if (! $image) {
                return null;
            }

            $image = self::applyOrientation($image, $orientation);
            $image = self::scaleDown($image, $type === IMAGETYPE_PNG);

            ob_start();
            if ($type === IMAGETYPE_JPEG) {
                imageinterlace($image, true); // progressive — renders sooner on slow connections
                imagejpeg($image, null, self::JPEG_QUALITY);
            } else {
                imagepng($image, null, 6);
            }
            $contents = (string) ob_get_clean();
            imagedestroy($image);

            return ['contents' => $contents, 'extension' => $type === IMAGETYPE_JPEG ? 'jpg' : 'png'];
        } finally {
            @ini_set('memory_limit', $previousLimit);
        }
    }

    private static function scaleDown(\GdImage $image, bool $keepAlpha): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= self::MAX_STORED_WIDTH) {
            return $image;
        }

        $newWidth = self::MAX_STORED_WIDTH;
        $newHeight = max(1, (int) round($height * $newWidth / $width));

        $scaled = imagecreatetruecolor($newWidth, $newHeight);

        if ($keepAlpha) {
            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
            imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        }

        imagecopyresampled($scaled, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $scaled;
    }

    private static function exifOrientation(string $path): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($path);

        return (int) ($exif['Orientation'] ?? 1);
    }

    private static function applyOrientation(\GdImage $image, int $orientation): \GdImage
    {
        return match ($orientation) {
            2 => self::flipped($image, IMG_FLIP_HORIZONTAL),
            3 => imagerotate($image, 180, 0),
            4 => self::flipped($image, IMG_FLIP_VERTICAL),
            5 => self::flipped(imagerotate($image, -90, 0), IMG_FLIP_HORIZONTAL),
            6 => imagerotate($image, -90, 0),
            7 => self::flipped(imagerotate($image, 90, 0), IMG_FLIP_HORIZONTAL),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private static function flipped(\GdImage $image, int $mode): \GdImage
    {
        imageflip($image, $mode);

        return $image;
    }
}
