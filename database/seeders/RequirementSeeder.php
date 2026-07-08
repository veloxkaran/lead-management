<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Database\Seeder;

class RequirementSeeder extends Seeder
{
    public function run(): void
    {
        $leadIds = Lead::pluck('id');
        $userIds = User::pluck('id');

        if ($leadIds->isEmpty() || $userIds->isEmpty()) {
            $this->command?->warn('Skipping RequirementSeeder: no leads or users found.');

            return;
        }

        // Note: created directly via the factory/model (not the RequirementService) so the
        // RequirementSaved event is never dispatched during seeding — avoids spamming Slack.
        Requirement::factory()
            ->count(20)
            ->state(fn () => [
                'lead_id' => $leadIds->random(),
                'created_by' => $userIds->random(),
                'assigned_to' => fake()->boolean(70) ? $userIds->random() : null,
            ])
            ->create();
    }
}
