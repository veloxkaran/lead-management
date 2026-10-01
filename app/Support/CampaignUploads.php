<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\CampaignAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns the composer's uploaded files into campaign attachments. Images
 * go through EmailImage (scaled to email width, JPEG metadata stripped) —
 * every recipient downloads them, so a phone photo mustn't go out at full
 * size. PDFs are kept as they are.
 *
 * Shrinking a phone photo takes the better part of a second, and the
 * composer uploads the same files twice (preview, then send) — so each
 * shrunk image is kept for a day under campaign-drafts/, keyed by the
 * original file's checksum, and the send reuses the preview's work.
 */
class CampaignUploads
{
    public const DRAFTS = 'campaign-drafts';

    private const DRAFT_HOURS = 24;

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{kind: string, contents: string, extension: string, mime: string, name: string}>
     */
    public static function prepare(array $files): array
    {
        $prepared = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            if ($file->getMimeType() === 'application/pdf') {
                $prepared[] = ['kind' => 'document', 'contents' => (string) file_get_contents($file->getRealPath()), 'extension' => 'pdf', 'mime' => 'application/pdf', 'name' => $file->getClientOriginalName()];

                continue;
            }

            $image = self::shrunk($file);
            $prepared[] = ['kind' => 'image', 'contents' => $image['contents'], 'extension' => $image['extension'], 'mime' => $image['extension'] === 'png' ? 'image/png' : 'image/jpeg', 'name' => $file->getClientOriginalName(), 'hash' => $image['hash']];
        }

        return $prepared;
    }

    /**
     * The shrunk image — from campaign-drafts/ when this exact file was
     * prepared recently (by the preview), otherwise made and kept there.
     *
     * @return array{contents: string, extension: string, hash: string}
     */
    private static function shrunk(UploadedFile $file): array
    {
        $disk = Storage::disk('local');
        $hash = sha1_file($file->getRealPath());

        foreach (['jpg', 'png'] as $extension) {
            $draft = self::DRAFTS."/{$hash}.{$extension}";
            if ($disk->exists($draft)) {
                return ['contents' => (string) $disk->get($draft), 'extension' => $extension, 'hash' => $hash];
            }
        }

        self::pruneDrafts();

        $image = EmailImage::prepare($file);
        $extension = $image['extension'] === 'jpeg' ? 'jpg' : $image['extension'];
        $disk->put(self::DRAFTS."/{$hash}.{$extension}", $image['contents']);

        return ['contents' => $image['contents'], 'extension' => $extension, 'hash' => $hash];
    }

    /**
     * Drafts from previews that were never sent.
     */
    private static function pruneDrafts(): void
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subHours(self::DRAFT_HOURS)->getTimestamp();

        foreach ($disk->files(self::DRAFTS) as $path) {
            if ($disk->lastModified($path) < $cutoff) {
                $disk->delete($path);
            }
        }
    }

    /**
     * Saves prepared files to the private disk as the campaign's attachments.
     */
    public static function store(Campaign $campaign, array $prepared): void
    {
        foreach (array_values($prepared) as $i => $file) {
            $path = CampaignAttachment::DIRECTORY."/{$campaign->id}/".($i + 1).'-'.Str::random(12).'.'.$file['extension'];
            Storage::disk('local')->put($path, $file['contents']);

            $campaign->attachments()->create([
                'kind' => $file['kind'],
                'disk_path' => $path,
                'original_name' => $file['name'],
                'mime' => $file['mime'],
                'size' => strlen($file['contents']),
                'sort' => $i,
            ]);

            // Saved for good now — the draft has done its job.
            if (isset($file['hash'])) {
                Storage::disk('local')->delete(self::DRAFTS."/{$file['hash']}.{$file['extension']}");
            }
        }
    }

    /**
     * For the preview before saving: the prepared files written to temp
     * files, in Campaign::mailAttachments() shape, plus the paths to delete
     * afterwards.
     *
     * @return array{0: array{images: array<int, array>, documents: array<int, array>}, 1: array<int, string>}
     */
    public static function temporary(array $prepared): array
    {
        $files = ['images' => [], 'documents' => []];
        $paths = [];

        foreach ($prepared as $file) {
            $path = tempnam(sys_get_temp_dir(), 'campaign-preview-');
            file_put_contents($path, $file['contents']);
            $paths[] = $path;

            $files[$file['kind'] === 'image' ? 'images' : 'documents'][] = [
                'path' => $path,
                'width' => $file['kind'] === 'image' ? EmailImage::displayWidth($path) : 0,
                'name' => $file['name'],
                'mime' => $file['mime'],
            ];
        }

        return [$files, $paths];
    }
}
