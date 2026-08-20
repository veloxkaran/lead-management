<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RequirementAttachment extends Model
{
    protected $fillable = ['requirement_id', 'disk_path', 'original_name', 'mime_type', 'size'];

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->disk_path);
    }
}
