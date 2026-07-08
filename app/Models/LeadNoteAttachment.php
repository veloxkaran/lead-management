<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LeadNoteAttachment extends Model
{
    protected $fillable = ['lead_note_id', 'disk_path', 'original_name', 'mime_type', 'size'];

    public function note(): BelongsTo
    {
        return $this->belongsTo(LeadNote::class, 'lead_note_id');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->disk_path);
    }
}
