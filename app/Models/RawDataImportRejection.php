<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawDataImportRejection extends Model
{
    use HasFactory;

    protected $fillable = ['raw_data_import_batch_id', 'row_number', 'errors', 'raw_data'];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'raw_data' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(RawDataImportBatch::class, 'raw_data_import_batch_id');
    }
}
