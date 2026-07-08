<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('role', 'user')->get();
        $statuses = LeadStatus::ordered()->get();

        if ($users->isEmpty() || $statuses->isEmpty()) {
            return;
        }

        collect(range(1, 35))->map(function () use ($users, $statuses) {
            $status = $statuses->random();

            return Lead::factory()->create([
                'assigned_user_id' => $users->random()->id,
                'created_by' => $users->random()->id,
                'lead_status_id' => $status->id,
                'archived_at' => fake()->boolean(10) ? now()->subDays(random_int(1, 30)) : null,
            ]);
        })->each(function (Lead $lead) use ($statuses) {
            $status = $statuses->firstWhere('id', $lead->lead_status_id);

            $lead->statusHistories()->create([
                'from_status_id' => null,
                'to_status_id' => $status->id,
                'changed_by' => $lead->created_by,
                'changed_at' => $lead->created_at,
            ]);

            if ($status->is_closed_won) {
                $lead->dealClosure()->create([
                    'closed_by' => $lead->assigned_user_id,
                    'closed_date' => now()->subDays(random_int(1, 20)),
                    'deal_value' => fake()->randomFloat(2, 1000, 50000),
                    'closing_comment' => 'Deal closed successfully.',
                ]);
            }

            if ($status->is_achievement) {
                $lead->update([
                    'achieved_at' => now()->subDays(random_int(1, 20)),
                    'achieved_cost' => fake()->randomFloat(2, 1000, (float) ($lead->opportunity_cost ?: 50000)),
                ]);
            }
        });
    }
}
