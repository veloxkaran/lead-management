<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * A file sent as a regular email attachment with an announcement (images
 * go inline instead — see AnnouncementImage).
 */
class AnnouncementDocument extends Model
{
    protected $fillable = ['announcement_id', 'disk_path', 'original_name', 'mime_type', 'size'];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function absolutePath(): string
    {
        return Storage::disk('public')->path($this->disk_path);
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size);
    }

    public function icon(): string
    {
        return match (strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION))) {
            'pdf' => 'bi-file-earmark-pdf text-danger',
            'doc', 'docx' => 'bi-file-earmark-word text-primary',
            'xls', 'xlsx', 'csv' => 'bi-file-earmark-excel text-success',
            'ppt', 'pptx' => 'bi-file-earmark-ppt text-warning',
            default => 'bi-file-earmark-text',
        };
    }
}
