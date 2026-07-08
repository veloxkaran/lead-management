<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'meeting_date' => fake()->dateTimeBetween('-2 weeks', '+3 weeks'),
            'meeting_time' => fake()->time('H:i:s'),
            'meeting_link' => 'https://meet.google.com/'.fake()->lexify('???-????-???'),
            'participants' => collect(range(1, fake()->numberBetween(2, 5)))->map(fn () => fake()->safeEmail())->all(),
            'team_id' => null,
            'created_by' => User::factory(),
        ];
    }
}
