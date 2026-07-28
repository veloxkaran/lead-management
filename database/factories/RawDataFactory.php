<?php

namespace Database\Factories;

use App\Enums\RawDataStatus;
use App\Models\RawData;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RawData>
 */
class RawDataFactory extends Factory
{
    protected $model = RawData::class;

    public function definition(): array
    {
        return [
            'contact_person' => fake()->name(),
            'phone' => fake()->unique()->numerify('98########'),
            'status' => RawDataStatus::New->value,
            'converted_lead_id' => null,
            'created_by' => User::factory(),
        ];
    }
}
