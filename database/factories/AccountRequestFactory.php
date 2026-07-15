<?php

namespace Database\Factories;

use App\Enums\AccountRequestType;
use App\Enums\RequirementStatus;
use App\Models\AccountRequest;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountRequest>
 */
class AccountRequestFactory extends Factory
{
    protected $model = AccountRequest::class;

    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'request_type' => AccountRequestType::Invoice->value,
            'amount' => fake()->randomFloat(2, 500, 20000),
            'details' => fake()->paragraph(),
            'status' => RequirementStatus::Pending->value,
            'requested_by' => User::factory(),
            'processed_by' => null,
        ];
    }
}
