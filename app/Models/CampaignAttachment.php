<?php

namespace App\Models;

use App\Support\EmailImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * An image shown inside a campaign email (embedded inline, cid:) or a PDF
 * attached to it. Stored on the private "local" disk.
 */
class CampaignAttachment extends Model
{
    public const DIRECTORY = 'campaign-attachments';

    protected $fillable = ['campaign_id', 'kind', 'disk_path', 'original_name', 'mime', 'size', 'sort'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function isImage(): bool
    {
        return $this->kind === 'image';
    }

    public function path(): string
    {
        return Storage::disk('local')->path($this->disk_path);
    }

    /**
     * @return array{path: string, width: int, name: string, mime: string}
     */
    public function forMail(): array
    {
        return [
            'path' => $this->path(),
            'width' => $this->isImage() ? EmailImage::displayWidth($this->path()) : 0,
            'name' => $this->original_name,
            'mime' => $this->mime,
        ];
    }
}
