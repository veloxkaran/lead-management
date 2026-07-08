<?php

namespace Database\Factories;

use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReleaseNote>
 */
class ReleaseNoteFactory extends Factory
{
    protected $model = ReleaseNote::class;

    public function definition(): array
    {
        return [
            'version' => 'v'.fake()->numberBetween(1, 3).'.'.fake()->numberBetween(0, 9).'.'.fake()->numberBetween(0, 9),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraphs(3, true),
            'release_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'google_drive_video_link' => null,
            'created_by' => User::factory(),
        ];
    }
}
