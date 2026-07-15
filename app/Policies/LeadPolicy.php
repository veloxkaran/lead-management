<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->isOwnerOrOverseer($user, $lead) || $this->hasBeenHandedOffTo($user, $lead);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->isOwnerOrOverseer($user, $lead) || $this->hasBeenHandedOffTo($user, $lead);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Pipeline-progression actions stay with whoever owns the sale — a
     * handoff to Customer Success or Finance grants them the lead's
     * details, not the ability to archive it or move it through statuses.
     */
    public function archive(User $user, Lead $lead): bool
    {
        return $this->isOwnerOrOverseer($user, $lead);
    }

    public function changeStatus(User $user, Lead $lead): bool
    {
        return $this->isOwnerOrOverseer($user, $lead);
    }

    public function close(User $user, Lead $lead): bool
    {
        return $this->isOwnerOrOverseer($user, $lead);
    }

    private function isOwnerOrOverseer(User $user, Lead $lead): bool
    {
        return $user->isOverseer()
            || $lead->assigned_user_id === $user->id || $lead->created_by === $user->id;
    }

    /**
     * WhatsApp access is intentionally exclusive: unlike the rest of the
     * lead, being the assigned owner or an overseer does NOT grant chat
     * access — only Super Admin (who configures the assignments) and the
     * users Super Admin has explicitly assigned can see or send messages.
     */
    public function chatWhatsapp(User $user, Lead $lead): bool
    {
        return $user->isSuperAdmin() || $lead->whatsappUsers()->where('user_id', $user->id)->exists();
    }

    public function manageWhatsappUsers(User $user, Lead $lead): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Once a lead has an Implementation Request or Support Ticket (Customer
     * Success) or an Account Request (Finance) raised against it, that
     * department's whole team can view and update the lead — not just the
     * one request record.
     */
    private function hasBeenHandedOffTo(User $user, Lead $lead): bool
    {
        return ($user->isCustomerSuccess() && $lead->isHandedOffToCustomerSuccess())
            || ($user->isFinance() && $lead->isHandedOffToFinance());
    }
}
