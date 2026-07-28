<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the "Responsibilities" and "Success Matrix" panels of the
 * RolePlaybook dashboard greeting card, per explicit user request — only
 * the "Motivation" panel (+ rotating quote) remains.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_playbooks', function (Blueprint $table) {
            $table->dropColumn(['responsibilities', 'success_metrics']);
        });
    }

    public function down(): void
    {
        Schema::table('role_playbooks', function (Blueprint $table) {
            $table->json('responsibilities')->nullable();
            $table->json('success_metrics')->nullable();
        });

        $data = [
            'super_admin' => [
                'responsibilities' => [
                    'Own platform configuration — roles, statuses, teams, and settings.',
                    'Manage user accounts across every department.',
                    'Hold company-wide reporting and system health.',
                    'Be the final escalation point when no other role can resolve something.',
                ],
                'success_metrics' => [
                    'Platform uptime and data integrity',
                    'User adoption across every department',
                    'Time-to-resolution on escalated issues',
                    'Accuracy of company-wide reporting',
                ],
            ],
            'manager' => [
                'responsibilities' => [
                    'Oversee the full pipeline across Business Development, Customer Success, and Finance.',
                    'View and process leads, implementation requests, and account requests that need a decision.',
                    'Raise support tickets for Customer Success when a client-facing issue surfaces.',
                    'Unblock stuck work across departments before it becomes a delay.',
                ],
                'success_metrics' => [
                    'Overall pipeline conversion rate',
                    'Goal achievement % across teams',
                    'Average time items sit unprocessed',
                    'Escalations resolved same-day',
                ],
            ],
            'business_development' => [
                'responsibilities' => [
                    'Own your assigned leads end-to-end — first contact through close.',
                    'Log every call, meeting, and note as it happens.',
                    'Keep follow-ups current so nothing goes cold.',
                    'Raise an Implementation Request the moment a deal closes, and an Account Request once terms are agreed.',
                ],
                'success_metrics' => [
                    'Target vs. achieved this month',
                    'Conversion rate from first contact to close',
                    'Average deal cycle time',
                    'Follow-up on-time rate',
                ],
            ],
            'customer_success' => [
                'responsibilities' => [
                    'Pick up Implementation Requests as soon as a deal closes.',
                    'Resolve Support Tickets raised by Managers.',
                    'Own onboarding through go-live for every new client.',
                    'Keep the client informed at every stage of implementation.',
                ],
                'success_metrics' => [
                    'Average time from request to first response',
                    'Implementation completion rate this month',
                    'Support ticket resolution time',
                    'Client-reported satisfaction at handover',
                ],
            ],
            'finance' => [
                'responsibilities' => [
                    'Process Account Requests raised by Business Development.',
                    'Verify pricing and terms before invoicing.',
                    'Track payments through to completion.',
                    'Keep the ledger current and accurate.',
                ],
                'success_metrics' => [
                    'Account requests processed within SLA',
                    'Outstanding vs. collected this month',
                    'Invoice accuracy rate',
                    'Average time from request to payment confirmation',
                ],
            ],
        ];

        foreach ($data as $role => $fields) {
            DB::table('role_playbooks')->where('role', $role)->update([
                'responsibilities' => json_encode($fields['responsibilities']),
                'success_metrics' => json_encode($fields['success_metrics']),
            ]);
        }
    }
};
