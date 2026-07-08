<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use Illuminate\Database\Seeder;

class LeadNoteSeeder extends Seeder
{
    public function run(): void
    {
        $leadIds = Lead::pluck('id');
        $userIds = User::pluck('id');

        if ($leadIds->isEmpty() || $userIds->isEmpty()) {
            $this->command?->warn('Skipping LeadNoteSeeder: no leads or users found.');

            return;
        }

        LeadNote::factory()
            ->count(20)
            ->state(fn () => [
                'lead_id' => $leadIds->random(),
                'author_id' => $userIds->random(),
            ])
            ->create();
    }
}
