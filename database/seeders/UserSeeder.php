<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo-only: generates fake regular users for local development. Not part
 * of the production seed path — see DemoDataSeeder.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $teams = Team::all();

        User::factory(8)->create()->each(function (User $user, int $index) use ($teams) {
            if ($teams->isNotEmpty()) {
                $user->update(['team_id' => $teams[$index % $teams->count()]->id]);
            }
        });
    }
}
