<?php

namespace Database\Factories;

use App\Models\SystemModule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SystemModule>
 */
class SystemModuleFactory extends Factory
{
    protected $model = SystemModule::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
