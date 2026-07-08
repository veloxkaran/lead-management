<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production-safe seed: functional reference data + one real Super
     * Admin account. No fake leads/users/teams/etc. are created here.
     *
     * For local development with sample data on top of this, run:
     *   php artisan db:seed --class=DemoDataSeeder
     */
    public function run(): void
    {
        $this->call([
            LeadStatusSeeder::class,
            KnowledgeBaseCategorySeeder::class,
            SettingSeeder::class,
            ProductionAdminSeeder::class,
        ]);
    }
}
