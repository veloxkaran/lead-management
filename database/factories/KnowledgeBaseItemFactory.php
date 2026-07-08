<?php

namespace Database\Factories;

use App\Enums\KnowledgeBaseType;
use App\Models\KnowledgeBaseCategory;
use App\Models\KnowledgeBaseItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeBaseItem>
 */
class KnowledgeBaseItemFactory extends Factory
{
    protected $model = KnowledgeBaseItem::class;

    public function definition(): array
    {
        return [
            'category_id' => KnowledgeBaseCategory::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'type' => KnowledgeBaseType::Link->value,
            'disk_path' => null,
            'link_url' => fake()->url(),
            'original_name' => null,
            'mime_type' => null,
            'size' => null,
            'uploaded_by' => User::factory(),
        ];
    }
}
