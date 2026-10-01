<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98'.fake()->unique()->numerify('########'),
        ];
    }
}
