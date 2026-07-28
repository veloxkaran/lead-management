<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the "SOPs" panel of the RolePlaybook dashboard greeting card, per
 * explicit user request to remove SOP content from the dashboard. This is
 * the RolePlaybook model's own unrelated `sops` text-bullet field (static
 * per-role guidance, no relation to the PolicyDocument/SOP feature removed
 * in 2026_07_28_090000) — that removal left this as the only remaining
 * "SOPs" wording anywhere in the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_playbooks', function (Blueprint $table) {
            $table->dropColumn('sops');
        });
    }

    public function down(): void
    {
        Schema::table('role_playbooks', function (Blueprint $table) {
            $table->json('sops')->nullable();
        });

        $sops = [
            'super_admin' => [
                'Review new user requests and role assignments weekly.',
                'Audit role assignments monthly — every account should match a real job.',
                'Back up the database before any schema or bulk-data change.',
                'Review release notes before they go out to the team.',
            ],
            'manager' => [
                'Review the cross-department queue every morning.',
                'Clear anything overdue before end of day, or explain why it is still open.',
                'Escalate to the Super Admin only what genuinely needs platform-level configuration.',
                'Raise a support ticket the moment a client-facing issue cannot be resolved at the rep level.',
            ],
            'business_development' => [
                'Log an activity the same business day it happens.',
                "Never leave a follow-up overdue without a note explaining why.",
                'On Closed-Won, raise the Implementation Request and Account Request before moving to the next lead.',
                'Keep lead status current — it drives every report built on top of it.',
            ],
            'customer_success' => [
                'Acknowledge a new Implementation Request within one business day.',
                'Update status as work progresses — do not let a request sit at Pending.',
                'Treat a Support Ticket as time-sensitive — it exists because a client already has a problem.',
                'Close the loop with the client before marking anything Completed.',
            ],
            'finance' => [
                "Verify the requested amount against the lead's closed deal value before processing.",
                'Flag discrepancies back to the requester immediately — do not silently correct them.',
                'Mark a request Completed only once payment is confirmed, not just invoiced.',
            ],
        ];

        foreach ($sops as $role => $items) {
            DB::table('role_playbooks')->where('role', $role)->update(['sops' => json_encode($items)]);
        }
    }
};
