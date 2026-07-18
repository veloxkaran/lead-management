<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Fake/demo data for local development and staging demos only.
 * Run explicitly with: php artisan db:seed --class=DemoDataSeeder
 *
 * Never run this against a production database — it creates fake users,
 * leads, and activity so the UI has something to show. Requires
 * the production-safe seed (LeadStatusSeeder, SettingSeeder, etc., run by
 * DatabaseSeeder) to already exist.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,

            LeadSeeder::class,
            LeadActivitySeeder::class,
            LeadNoteSeeder::class,
            FollowUpSeeder::class,
            RequirementSeeder::class,

            GoalSeeder::class,
            DailySummarySeeder::class,

            ReleaseNoteSeeder::class,
            KnowledgeBaseItemSeeder::class,
            MeetingSeeder::class,
        ]);
    }
}
