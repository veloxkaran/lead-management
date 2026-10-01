<?php

namespace Database\Factories;

use App\Enums\CampaignAudience;
use App\Enums\CampaignChannel;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(3, true)),
            'channel' => CampaignChannel::Email,
            'subject' => fake()->sentence(4),
            'message' => 'Hi {{name}}, '.fake()->sentence(),
            'audience' => CampaignAudience::None,
            'status' => CampaignStatus::Completed,
            'recipient_count' => 0,
            'created_by' => User::factory(),
        ];
    }

    public function sms(): static
    {
        return $this->state(['channel' => CampaignChannel::Sms, 'subject' => null]);
    }
}
