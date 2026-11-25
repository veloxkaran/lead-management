<?php

namespace Tests\Feature;

use App\Enums\CampaignChannel;
use App\Models\Setting;
use App\Support\CampaignSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Email campaigns wait an hour between batches.
 */
class CampaignBatchPauseTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_25_090000_raise_campaign_batch_pause_to_one_hour.php');
    }

    public function test_the_default_pause_between_email_batches_is_one_hour(): void
    {
        $this->assertSame(60, config('campaigns.batch_pause_minutes'));
        $this->assertSame(60, app(CampaignSettings::class)->sendOptions(CampaignChannel::Email)['pause_minutes']);
    }

    public function test_the_saved_old_default_moves_to_one_hour(): void
    {
        Setting::set('campaign_batch_pause_minutes', '15');
        $this->assertSame('15', Setting::get('campaign_batch_pause_minutes')); // now cached

        $this->migration()->up();

        $this->assertSame('60', Setting::get('campaign_batch_pause_minutes'), 'cache cleared, new value read');
        $this->assertSame(60, app(CampaignSettings::class)->sendOptions(CampaignChannel::Email)['pause_minutes']);
    }

    public function test_a_deliberately_chosen_pause_is_left_alone(): void
    {
        Setting::set('campaign_batch_pause_minutes', '30');

        $this->migration()->up();

        $this->assertSame('30', Setting::get('campaign_batch_pause_minutes'));
    }
}
