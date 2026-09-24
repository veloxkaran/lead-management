<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AnnouncementImage extends Model
{
    protected $fillable = ['announcement_id', 'disk_path', 'original_name'];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->disk_path);
    }

    public function absolutePath(): string
    {
        return Storage::disk('public')->path($this->disk_path);
    }
}
