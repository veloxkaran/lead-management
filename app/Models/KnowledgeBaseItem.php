<?php

namespace App\Models;

use App\Enums\KnowledgeBaseType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Mews\Purifier\Facades\Purifier;

class KnowledgeBaseItem extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'category_id', 'title', 'description', 'type', 'disk_path', 'link_url',
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

    /**
     * `description` is saved already-sanitized by KnowledgeBaseItemService,
     * but this re-cleans on read too so older rows written before the
     * rich-text editor existed (plain text, possibly with stray "<"/">")
     * still render safely as HTML instead of being interpreted as broken
     * markup.
     */
    public function descriptionHtml(): string
    {
        return Purifier::clean((string) $this->description);
    }

    public function url(): ?string
    {
        if ($this->type === KnowledgeBaseType::Link) {
            return $this->link_url;
        }

        return $this->disk_path ? Storage::disk('public')->url($this->disk_path) : null;
    }
}
