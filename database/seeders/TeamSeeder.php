<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Sales', 'Marketing', 'Enterprise Accounts'] as $name) {
            Team::firstOrCreate(['name' => $name], ['description' => "{$name} team"]);
        }
    }
}
