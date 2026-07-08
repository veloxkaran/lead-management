<?php

namespace Database\Factories;

use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_name' => fake()->unique()->company(),
            'contact_person' => fake()->name(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'website' => fake()->url(),
            'industry' => fake()->randomElement(['Software', 'Manufacturing', 'Retail', 'Healthcare', 'Finance', 'Education']),
            'number_of_employees' => fake()->numberBetween(5, 5000),
            'business_details' => fake()->paragraph(),
            'about_client_business' => fake()->paragraph(),
            'source' => fake()->randomElement(['Referral', 'Website', 'LinkedIn', 'Cold Call', 'Event']),
            'opportunity_cost' => fake()->randomFloat(2, 1000, 60000),
            'achieved_cost' => 0,
            'assigned_user_id' => User::factory(),
            'lead_status_id' => LeadStatus::factory(),
            'created_by' => User::factory(),
        ];
    }
}
