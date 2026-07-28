<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawDataComment extends Model
{
    use HasFactory;

    protected $fillable = ['raw_data_id', 'author_id', 'comment'];

    public function rawData(): BelongsTo
    {
        return $this->belongsTo(RawData::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
