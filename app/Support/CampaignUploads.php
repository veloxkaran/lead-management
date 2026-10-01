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
 */
class CampaignUploads
{
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

            $image = EmailImage::prepare($file);
            $extension = $image['extension'] === 'jpeg' ? 'jpg' : $image['extension'];
            $prepared[] = ['kind' => 'image', 'contents' => $image['contents'], 'extension' => $extension, 'mime' => $extension === 'png' ? 'image/png' : 'image/jpeg', 'name' => $file->getClientOriginalName()];
        }

        return $prepared;
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
