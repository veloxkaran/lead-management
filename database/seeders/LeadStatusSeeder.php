<?php

namespace Database\Seeders;

use App\Models\LeadStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LeadStatusSeeder extends Seeder
{
    /**
     * Seed the fixed pipeline of lead statuses.
     *
     * IMPORTANT: This seeder must run very early, before any Lead-related
     * seeding, since leads reference lead_status_id and LeadService::close()
     * looks up the exact slug "converted-to-customer".
     */
    public function run(): void
    {
        $statuses = [
            ['name' => 'New', 'color' => '#0d6efd', 'is_default' => true],
            ['name' => 'Emailed', 'color' => '#6f42c1', 'is_default' => false],
            ['name' => 'Phoned', 'color' => '#6610f2', 'is_default' => false],
            ['name' => 'Proposal Sent', 'color' => '#fd7e14', 'is_default' => false],
            ['name' => 'Financial Proposal Sent', 'color' => '#e83e8c', 'is_default' => false],
            ['name' => 'Negotiating', 'color' => '#ffc107', 'is_default' => false],
            ['name' => 'Agreement Sent', 'color' => '#20c997', 'is_default' => false],
            ['name' => 'Contract Done', 'color' => '#0dcaf0', 'is_default' => false],
            ['name' => 'Hold', 'color' => '#adb5bd', 'is_default' => false, 'is_closed_lost' => true],
            ['name' => 'On Demo', 'color' => '#198754', 'is_default' => false],
            ['name' => 'On Trial', 'color' => '#6c757d', 'is_default' => false],
            ['name' => 'On Implementation', 'color' => '#0a58ca', 'is_default' => false],
            ['name' => 'Converted to Customer', 'color' => '#146c43', 'is_default' => false, 'is_closed_won' => true, 'is_achievement' => true],
        ];

        foreach ($statuses as $order => $status) {
            $slug = Str::slug($status['name']);

            LeadStatus::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $status['name'],
                    'color' => $status['color'],
                    'order' => $order,
                    'is_default' => $status['is_default'] ?? false,
                    'is_closed_won' => $status['is_closed_won'] ?? false,
                    'is_closed_lost' => $status['is_closed_lost'] ?? false,
                    'is_achievement' => $status['is_achievement'] ?? false,
                ]
            );
        }
    }
}
