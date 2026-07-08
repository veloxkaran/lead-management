<?php

namespace Database\Seeders;

use App\Models\Meeting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MeetingSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $teams = Team::all();

        if ($users->isEmpty()) {
            return;
        }

        for ($i = 1; $i <= 10; $i++) {
            $creator = $users->random();
            $isTeamMeeting = $teams->isNotEmpty() && $i % 2 === 0;
            $daysOffset = rand(-15, 20);

            Meeting::create([
                'title' => $isTeamMeeting ? "Team Sync #{$i}" : "1:1 Meeting #{$i}",
                'meeting_date' => now()->addDays($daysOffset)->toDateString(),
                'meeting_time' => fake()->time('H:i:s'),
                'meeting_link' => 'https://meet.google.com/'.strtolower(Str::random(3).'-'.Str::random(4).'-'.Str::random(3)),
                'participants' => collect(range(1, rand(2, 5)))->map(fn () => fake()->safeEmail())->all(),
                'team_id' => $isTeamMeeting ? $teams->random()->id : null,
                'created_by' => $creator->id,
            ]);
        }
    }
}
