<?php

namespace Database\Seeders;

use App\Enums\FollowUpStatus;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Seeder;

class FollowUpSeeder extends Seeder
{
    public function run(): void
    {
        $leadIds = Lead::pluck('id');
        $userIds = User::pluck('id');

        if ($leadIds->isEmpty() || $userIds->isEmpty()) {
            $this->command?->warn('Skipping FollowUpSeeder: no leads or users found.');

            return;
        }

        // Past follow-ups: a mix of completed and cancelled.
        FollowUp::factory()
            ->count(15)
            ->state(fn () => [
                'status' => fake()->randomElement([FollowUpStatus::Completed, FollowUpStatus::Cancelled])->value,
                'lead_id' => $leadIds->random(),
                'created_by' => $userIds->random(),
            ])
            ->past()
            ->create();

        // Future follow-ups: still pending.
        FollowUp::factory()
            ->count(10)
            ->state(fn () => [
                'lead_id' => $leadIds->random(),
                'created_by' => $userIds->random(),
            ])
            ->upcoming()
            ->create();
    }
}
