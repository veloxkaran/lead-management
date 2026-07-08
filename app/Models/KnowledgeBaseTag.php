<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KnowledgeBaseTag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeBaseItem::class, 'knowledge_base_item_tag', 'knowledge_base_tag_id', 'knowledge_base_item_id');
    }
}
