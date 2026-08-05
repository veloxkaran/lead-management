<?php

namespace App\Models;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'lead_id', 'subject', 'details', 'priority', 'status', 'raised_by', 'assigned_to', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => RequirementPriority::class,
            'status' => RequirementStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SupportTicketComment::class)->oldest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class)->latest();
    }

    /**
     * The subject/details describing the ticket lock 12 hours after it's
     * raised — unlike priority/status/assignment, which stay editable for
     * the ticket's whole working life since that's how its workflow
     * progresses.
     */
    public function detailsEditable(): bool
    {
        return $this->created_at->addHours(12)->isFuture();
    }

    /**
     * Minutes since the ticket was raised — up to now while it's still
     * open, or up to resolved_at once it's been solved (so the value
     * freezes at resolution instead of continuing to climb).
     */
    public function elapsedMinutes(): int
    {
        return $this->created_at->diffInMinutes($this->resolved_at ?? now());
    }

    /**
     * elapsedMinutes() broken into "day:hour:minute", e.g. "2:03:07" for
     * 2 days, 3 hours, 7 minutes.
     */
    public function elapsedFormatted(): string
    {
        $totalMinutes = $this->elapsedMinutes();

        $days = intdiv($totalMinutes, 1440);
        $hours = intdiv($totalMinutes % 1440, 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%d:%02d:%02d', $days, $hours, $minutes);
    }
}
