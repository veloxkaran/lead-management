<?php

namespace App\Models;

use App\Enums\KnowledgeBaseType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class KnowledgeBaseItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'title', 'description', 'type', 'disk_path', 'link_url',
        'original_name', 'mime_type', 'size', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => KnowledgeBaseType::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseCategory::class, 'category_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeBaseTag::class, 'knowledge_base_item_tag', 'knowledge_base_item_id', 'knowledge_base_tag_id');
    }

    public function url(): ?string
    {
        if ($this->type === KnowledgeBaseType::Link) {
            return $this->link_url;
        }

        return $this->disk_path ? Storage::disk('public')->url($this->disk_path) : null;
    }
}
