<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Database\Seeder;

class LeadActivitySeeder extends Seeder
{
    public function run(): void
    {
        $leadIds = Lead::pluck('id');
        $userIds = User::pluck('id');

        if ($leadIds->isEmpty() || $userIds->isEmpty()) {
            $this->command?->warn('Skipping LeadActivitySeeder: no leads or users found.');

            return;
        }

        LeadActivity::factory()
            ->count(40)
            ->state(fn () => [
                'lead_id' => $leadIds->random(),
                'created_by' => $userIds->random(),
            ])
            ->create();
    }
}
