<?php

namespace App\Models;

use App\Enums\RawDataStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RawData extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'raw_data';

    protected $fillable = [
        'company_id', 'contact_person', 'phone', 'email', 'source', 'status', 'converted_lead_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RawDataStatus::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertedLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'converted_lead_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RawDataComment::class)->oldest();
    }

    public function isNew(): bool
    {
        return $this->status === RawDataStatus::New;
    }
}
