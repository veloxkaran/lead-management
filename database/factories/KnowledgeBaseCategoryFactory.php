<?php

namespace Database\Factories;

use App\Models\KnowledgeBaseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KnowledgeBaseCategory>
 */
class KnowledgeBaseCategoryFactory extends Factory
{
    protected $model = KnowledgeBaseCategory::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
