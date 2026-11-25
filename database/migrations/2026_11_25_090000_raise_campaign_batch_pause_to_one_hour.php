<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Email campaigns now wait an hour between batches (was 15 minutes). The
 * pause is a Campaign Setup setting saved in `settings`, so the new default
 * in config/campaigns.php alone wouldn't reach a site that already saved
 * 15 — move that saved old default to 60. A different value someone chose
 * deliberately is left alone. Campaigns already created keep the pause they
 * were created with.
 */
return new class extends Migration
{
    private const KEY = 'campaign_batch_pause_minutes';

    public function up(): void
    {
        DB::table('settings')->where('key', self::KEY)->where('value', '15')->update(['value' => '60', 'updated_at' => now()]);

        Cache::forget('settings.all'); // Setting::get() caches every setting forever
    }

    public function down(): void
    {
        DB::table('settings')->where('key', self::KEY)->where('value', '60')->update(['value' => '15', 'updated_at' => now()]);

        Cache::forget('settings.all');
    }
};
