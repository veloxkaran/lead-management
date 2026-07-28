<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use App\Services\OrganizationHierarchyService;

class LeadPolicy
{
    public function __construct(protected OrganizationHierarchyService $hierarchy) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Open to every user, not just the assignee/creator/reporting-manager
     * chain or a department that's been handed off to — every field change
     * is logged (see LeadService::update()'s diff logging), so opening
     * visibility/editing this wide stays auditable rather than anonymous.
     */
    public function view(User $user, Lead $lead): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $lead): bool
    {
        return true;
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

    /**
     * Gates the Training progress card on the Lead page — narrower than
     * view(): the BD rep who owns the lead can see the page but not this
     * card, since progress tracking is a Customer Success/management
     * concern, not a sales one. Reporting-line managers of the assigned
     * salesperson get read-only visibility via the org hierarchy,
     * independent of whether they hold a special role — editing still
     * requires the per-module policy to pass too.
     *
     * Deliberately uses getAllSubordinateIds() rather than canView(): the
     * latter treats a user as able to view themselves, which would grant
     * the assigned rep visibility into their own card — exactly what this
     * method exists to exclude.
     */
    public function viewProgressStatus(User $user, Lead $lead): bool
    {
        return $user->isSuperAdmin()
            || $user->isManager()
            || $user->isCustomerSuccess()
            || ($lead->assignedUser
                && $lead->assigned_user_id !== $user->id
                && $this->hierarchy->getAllSubordinateIds($user)->contains($lead->assigned_user_id));
    }

    /**
     * True for the literal owner/creator or an overseer (unchanged), or for
     * anyone whose reporting hierarchy (direct + indirect reports) includes
     * the lead's assignee or creator — e.g. a senior rep whose junior report
     * created or is working the lead.
     */
    private function isOwnerOrOverseer(User $user, Lead $lead): bool
    {
        if ($user->isOverseer()
            || $lead->assigned_user_id === $user->id
            || $lead->created_by === $user->id) {
            return true;
        }

        $subordinateIds = $this->hierarchy->getAllSubordinateIds($user);

        return $subordinateIds->contains($lead->assigned_user_id)
            || $subordinateIds->contains($lead->created_by);
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
     * The full-history PDF pulls together every module's records for this
     * lead (requirements, support tickets + comments, tasks + comments,
     * account handoffs, etc.) into one document — restricted to Super Admin
     * only, unlike view()/update() which are open to everyone, since this
     * bypasses each module's own per-record visibility rules to compile
     * everything in one place.
     */
    public function exportPdf(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
