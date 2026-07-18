<?php

namespace Database\Seeders;

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
        User::factory(8)->create();
    }
}
