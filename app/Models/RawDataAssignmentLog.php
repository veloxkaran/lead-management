<?php

namespace App\Models;

use App\Enums\RawDataAssignmentAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawDataAssignmentLog extends Model
{
    use HasFactory;

    protected $fillable = ['raw_data_id', 'action', 'user_id', 'performed_by'];

    protected function casts(): array
    {
        return [
            'action' => RawDataAssignmentAction::class,
        ];
    }

    public function rawData(): BelongsTo
    {
        return $this->belongsTo(RawData::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
