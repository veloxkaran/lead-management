<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $playbooks = [
            [
                'role' => 'super_admin',
                'responsibilities' => [
                    'Own platform configuration — roles, statuses, teams, and settings.',
                    'Manage user accounts across every department.',
                    'Hold company-wide reporting and system health.',
                    'Be the final escalation point when no other role can resolve something.',
                ],
                'sops' => [
                    'Review new user requests and role assignments weekly.',
                    'Audit role assignments monthly — every account should match a real job.',
                    'Back up the database before any schema or bulk-data change.',
                    'Review release notes before they go out to the team.',
                ],
                'success_metrics' => [
                    'Platform uptime and data integrity',
                    'User adoption across every department',
                    'Time-to-resolution on escalated issues',
                    'Accuracy of company-wide reporting',
                ],
                'motivation' => "You're the one who keeps the whole system honest — every number downstream depends on what you configure right today.",
            ],
            [
                'role' => 'manager',
                'responsibilities' => [
                    'Oversee the full pipeline across Business Development, Customer Success, and Finance.',
                    'View and process leads, implementation requests, and account requests that need a decision.',
                    'Raise support tickets for Customer Success when a client-facing issue surfaces.',
                    'Unblock stuck work across departments before it becomes a delay.',
                ],
                'sops' => [
                    'Review the cross-department queue every morning.',
                    'Clear anything overdue before end of day, or explain why it is still open.',
                    'Escalate to the Super Admin only what genuinely needs platform-level configuration.',
                    'Raise a support ticket the moment a client-facing issue cannot be resolved at the rep level.',
                ],
                'success_metrics' => [
                    'Overall pipeline conversion rate',
                    'Goal achievement % across teams',
                    'Average time items sit unprocessed',
                    'Escalations resolved same-day',
                ],
                'motivation' => "Nothing should stall because it was waiting on you — you're the unblock button for the whole team.",
            ],
            [
                'role' => 'business_development',
                'responsibilities' => [
                    'Own your assigned leads end-to-end — first contact through close.',
                    'Log every call, meeting, and note as it happens.',
                    'Keep follow-ups current so nothing goes cold.',
                    'Raise an Implementation Request the moment a deal closes, and an Account Request once terms are agreed.',
                ],
                'sops' => [
                    'Log an activity the same business day it happens.',
                    "Never leave a follow-up overdue without a note explaining why.",
                    'On Closed-Won, raise the Implementation Request and Account Request before moving to the next lead.',
                    'Keep lead status current — it drives every report built on top of it.',
                ],
                'success_metrics' => [
                    'Target vs. achieved this month',
                    'Conversion rate from first contact to close',
                    'Average deal cycle time',
                    'Follow-up on-time rate',
                ],
                'motivation' => "Every lead you nurture today is next quarter's signed contract — the pipeline only moves as fast as you keep it moving.",
            ],
            [
                'role' => 'customer_success',
                'responsibilities' => [
                    'Pick up Implementation Requests as soon as a deal closes.',
                    'Resolve Support Tickets raised by Managers.',
                    'Own onboarding through go-live for every new client.',
                    'Keep the client informed at every stage of implementation.',
                ],
                'sops' => [
                    'Acknowledge a new Implementation Request within one business day.',
                    'Update status as work progresses — do not let a request sit at Pending.',
                    'Treat a Support Ticket as time-sensitive — it exists because a client already has a problem.',
                    'Close the loop with the client before marking anything Completed.',
                ],
                'success_metrics' => [
                    'Average time from request to first response',
                    'Implementation completion rate this month',
                    'Support ticket resolution time',
                    'Client-reported satisfaction at handover',
                ],
                'motivation' => "The sale gets a client in the door — you're the reason they stay.",
            ],
            [
                'role' => 'finance',
                'responsibilities' => [
                    'Process Account Requests raised by Business Development.',
                    'Verify pricing and terms before invoicing.',
                    'Track payments through to completion.',
                    'Keep the ledger current and accurate.',
                ],
                'sops' => [
                    "Verify the requested amount against the lead's closed deal value before processing.",
                    'Flag discrepancies back to the requester immediately — do not silently correct them.',
                    'Mark a request Completed only once payment is confirmed, not just invoiced.',
                ],
                'success_metrics' => [
                    'Account requests processed within SLA',
                    'Outstanding vs. collected this month',
                    'Invoice accuracy rate',
                    'Average time from request to payment confirmation',
                ],
                'motivation' => 'Every number you confirm is one the whole company relies on to know where it really stands.',
            ],
        ];

        foreach ($playbooks as $playbook) {
            DB::table('role_playbooks')->updateOrInsert(
                ['role' => $playbook['role']],
                [
                    'responsibilities' => json_encode($playbook['responsibilities']),
                    'sops' => json_encode($playbook['sops']),
                    'success_metrics' => json_encode($playbook['success_metrics']),
                    'motivation' => $playbook['motivation'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('role_playbooks')->whereIn('role', [
            'super_admin', 'manager', 'business_development', 'customer_success', 'finance',
        ])->delete();
    }
};
