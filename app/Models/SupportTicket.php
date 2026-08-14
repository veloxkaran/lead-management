<?php

namespace App\Models;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksResolutionTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use BelongsToCompany, HasFactory, TracksResolutionTime;

    protected $fillable = [
        'company_id', 'lead_id', 'subject', 'details', 'priority', 'status', 'raised_by',
        'assigned_to', 'assigned_by', 'assigned_at', 'resolved_at', 'is_client_submitted',
    ];

    protected function casts(): array
    {
        return [
            'priority' => RequirementPriority::class,
            'status' => RequirementStatus::class,
            'assigned_at' => 'datetime',
            'resolved_at' => 'datetime',
            'is_client_submitted' => 'boolean',
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

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SupportTicketComment::class)->oldest();
    }

    public function assignmentLogs(): HasMany
    {
        return $this->hasMany(SupportTicketAssignmentLog::class)->latest();
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

    protected function resolvedAtColumn(): string
    {
        return 'resolved_at';
    }

    /**
     * `raised_by` always points at a real staff member even for
     * client-submitted tickets — it has to, for FK integrity, since this
     * app has no system/bot-user account and the column is NOT NULL (see
     * SupportTicketService::createFromClientPortal()) — so every view
     * renders through this instead of `$ticket->raiser?->name` directly, to
     * avoid crediting that staff member with something the client actually
     * submitted.
     */
    public function raiserDisplayName(): string
    {
        return $this->is_client_submitted
            ? 'Client (self-service portal)'
            : ($this->raiser?->name ?? 'Unknown');
    }

    protected static function noResolvedRecordsMessage(): string
    {
        return 'No resolved tickets yet';
    }
}
