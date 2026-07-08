<?php

namespace Database\Seeders;

use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReleaseNoteSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::where('role', 'super_admin')->first() ?? User::first();

        if (! $creator) {
            return;
        }

        $releases = [
            [
                'version' => 'v1.0.0',
                'title' => 'Initial Launch',
                'description' => 'Welcome to the first release of '.config('app.name')."!\n\n- Lead management module\n- Basic dashboard\n- User authentication",
                'release_date' => now()->subWeeks(20)->toDateString(),
            ],
            [
                'version' => 'v1.1.0',
                'title' => 'Activity Tracking & Notes',
                'description' => "- Added lead activity timeline\n- Added notes with attachments\n- Minor UI polish",
                'release_date' => now()->subWeeks(17)->toDateString(),
            ],
            [
                'version' => 'v1.2.0',
                'title' => 'Follow-ups & Reminders',
                'description' => "- Follow-up scheduling\n- Email reminders\n- Bug fixes for lead status transitions",
                'release_date' => now()->subWeeks(14)->toDateString(),
            ],
            [
                'version' => 'v1.3.0',
                'title' => 'Requirements Module',
                'description' => "- New requirements tracker per lead\n- Priority levels and assignees\n- Performance improvements",
                'release_date' => now()->subWeeks(10)->toDateString(),
            ],
            [
                'version' => 'v2.0.0',
                'title' => 'Team Goals & Reporting',
                'description' => "- Team goal tracking\n- Daily summary reports\n- Redesigned dashboard with charts",
                'release_date' => now()->subWeeks(6)->toDateString(),
            ],
            [
                'version' => 'v2.1.0',
                'title' => 'Knowledge Base & Meetings',
                'description' => "- New Knowledge Base module for shared resources\n- Meetings scheduling with Google Meet links\n- Release notes hub (this page!)",
                'release_date' => now()->subWeeks(2)->toDateString(),
            ],
        ];

        foreach ($releases as $release) {
            ReleaseNote::create($release + ['created_by' => $creator->id]);
        }
    }
}
