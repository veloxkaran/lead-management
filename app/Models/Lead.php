<?php

namespace App\Models;

use App\Enums\WhatsappMessageDirection;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'company_name', 'contact_person', 'email', 'phone', 'whatsapp_number',
        'address', 'website',
        'industry', 'number_of_employees', 'business_details', 'about_client_business',
        'source', 'opportunity_cost', 'achieved_cost', 'achieved_at',
        'assigned_user_id', 'lead_status_id', 'created_by', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'achieved_at' => 'datetime',
            'number_of_employees' => 'integer',
            'opportunity_cost' => 'decimal:2',
            'achieved_cost' => 'decimal:2',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'lead_status_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class)->latest('changed_at');
    }

    /**
     * The single most recent status transition, for efficient one-per-lead
     * eager loading (avoids pulling the full history just to know how long
     * a lead has been sitting in its current status).
     */
    public function latestStatusHistory(): HasOne
    {
        return $this->hasOne(LeadStatusHistory::class)->latestOfMany('changed_at');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest('activity_date')->latest('activity_time');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest();
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }

    public function dealClosure(): HasOne
    {
        return $this->hasOne(DealClosure::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }

    public function latestTraining(): HasOne
    {
        return $this->hasOne(Training::class)->latestOfMany('created_at');
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function whatsappUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'lead_whatsapp_user');
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class)->oldest();
    }

    /**
     * The single most recent inbound message, for cheaply eager-loading the
     * 24-hour customer-service window state without pulling the full thread.
     */
    public function lastInboundWhatsappMessage(): HasOne
    {
        return $this->hasOne(WhatsappMessage::class)
            ->where('direction', WhatsappMessageDirection::Inbound)
            ->latestOfMany('wa_timestamp');
    }

    /**
     * Meta only allows free-form replies within 24 hours of the customer's
     * last message — outside that window, only approved templates can be sent.
     */
    public function isWhatsappWindowOpen(): bool
    {
        $lastInbound = $this->relationLoaded('lastInboundWhatsappMessage')
            ? $this->lastInboundWhatsappMessage
            : $this->lastInboundWhatsappMessage()->first();

        $timestamp = $lastInbound?->wa_timestamp ?? $lastInbound?->created_at;

        return $timestamp !== null && $timestamp->gt(now()->subHours(24));
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeAchieved($query)
    {
        return $query->whereNotNull('achieved_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isAchieved(): bool
    {
        return $this->achieved_at !== null;
    }

    /**
     * When the lead entered its current status — the most recent status
     * transition's timestamp, or when the lead was created if it has never
     * changed status.
     */
    public function currentStatusSince(): \Illuminate\Support\Carbon
    {
        if ($this->relationLoaded('statusHistories')) {
            return $this->statusHistories->first()?->changed_at ?? $this->created_at;
        }

        return $this->latestStatusHistory?->changed_at ?? $this->created_at;
    }

    public function currentStatusAge(): string
    {
        return $this->currentStatusSince()->diffForHumans(null, true);
    }
}
