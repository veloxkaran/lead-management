<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RawDataImportBatch extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'user_id', 'source', 'original_filename',
        'total_rows', 'imported_count', 'updated_count', 'unchanged_count', 'rejected_count',
    ];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'imported_count' => 'integer',
            'updated_count' => 'integer',
            'unchanged_count' => 'integer',
            'rejected_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rejections(): HasMany
    {
        return $this->hasMany(RawDataImportRejection::class);
    }

    public function successfulCount(): int
    {
        return $this->imported_count + $this->updated_count + $this->unchanged_count;
    }
}
