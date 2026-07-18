<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'lead_id', 'plan_name', 'status', 'contract_start_date', 'expiry_date',
        'licensed_users', 'active_users', 'billing_cycle', 'renewal_amount', 'auto_renew',
        'last_payment_date', 'outstanding_amount', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'billing_cycle' => BillingCycle::class,
            'contract_start_date' => 'date',
            'expiry_date' => 'date',
            'last_payment_date' => 'date',
            'licensed_users' => 'integer',
            'active_users' => 'integer',
            'renewal_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'auto_renew' => 'boolean',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Computed at read time rather than stored, so it can never drift stale
     * between renders — unlike completion_percentage-style fields, this one
     * changes every single day regardless of any user action.
     */
    public function daysRemaining(): ?int
    {
        return $this->expiry_date ? now()->startOfDay()->diffInDays($this->expiry_date, false) : null;
    }

    public function isExpiringSoon(): bool
    {
        $days = $this->daysRemaining();

        return $days !== null && $days >= 0 && $days <= 30;
    }
}
