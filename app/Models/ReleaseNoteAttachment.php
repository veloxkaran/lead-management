<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ReleaseNoteAttachment extends Model
{
    protected $fillable = ['release_note_id', 'disk_path', 'original_name'];

    public function releaseNote(): BelongsTo
    {
        return $this->belongsTo(ReleaseNote::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->disk_path);
    }
}
