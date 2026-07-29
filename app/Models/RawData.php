<?php

namespace App\Models;

use App\Enums\RawDataStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class RawData extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'raw_data';

    /**
     * How long an assignee has to act before the assignment countdown
     * (resources/js/raw-data-countdown.js) flips to "overdue".
     */
    public const ASSIGNMENT_RESPONSE_HOURS = 48;

    protected $fillable = [
        'company_id', 'contact_person', 'company_name', 'phone', 'email', 'source', 'notes', 'status',
        'converted_lead_id', 'created_by', 'assigned_to', 'assigned_by', 'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RawDataStatus::class,
            'assigned_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function assignmentDeadline(): ?Carbon
    {
        return $this->assigned_at?->copy()->addHours(self::ASSIGNMENT_RESPONSE_HOURS);
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
