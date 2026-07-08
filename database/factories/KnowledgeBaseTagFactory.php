<?php

namespace Database\Factories;

use App\Models\KnowledgeBaseTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KnowledgeBaseTag>
 */
class KnowledgeBaseTagFactory extends Factory
{
    protected $model = KnowledgeBaseTag::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
