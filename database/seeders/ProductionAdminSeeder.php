<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductionAdminSeeder extends Seeder
{
    /**
     * Create (or update) the single real Super Admin account for a fresh
     * production database. Reads credentials from the environment so no
     * fake/demo identity ever ships in a production seed; if ADMIN_PASSWORD
     * isn't set, a random secure password is generated and printed once.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD');
        $generated = false;

        if (blank($password)) {
            $password = Str::password(16);
            $generated = true;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'System Administrator'),
                'password' => Hash::make($password),
                'role' => UserRole::SuperAdmin,
                'status' => UserStatus::Active,
                'department' => 'Management',
                'designation' => 'Super Admin',
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info("Super Admin ready: {$email}");

        if ($generated) {
            $this->command?->warn("Generated password (save this now, it will not be shown again): {$password}");
            $this->command?->warn('Set ADMIN_PASSWORD in .env before seeding to control this instead.');
        }
    }
}
