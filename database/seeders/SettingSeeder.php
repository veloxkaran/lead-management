<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        // Functional defaults only — no placeholder company/branding data.
        // Company info, Slack webhook, and Google links are left blank for
        // a Super Admin to fill in via the Settings page.
        Setting::set('notifications_email_enabled', '1');
        Setting::set('notifications_web_enabled', '1');
        Setting::set('dashboard_show_motivation_quote', '1');
        Setting::set('motivation_quote_api_url', 'https://api.quotable.io/random');
    }
}
