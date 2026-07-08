<?php

namespace Database\Seeders;

use App\Models\KnowledgeBaseCategory;
use App\Models\KnowledgeBaseItem;
use App\Models\KnowledgeBaseTag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KnowledgeBaseItemSeeder extends Seeder
{
    public function run(): void
    {
        $categories = KnowledgeBaseCategory::all();
        $uploader = User::first();

        if ($categories->isEmpty() || ! $uploader) {
            return;
        }

        $tagNames = [
            'onboarding', 'sales-playbook', 'template', 'pricing', 'competitor-analysis',
            'demo-script', 'contract-template', 'faq', 'brand-guidelines', 'product-sheet',
        ];

        $tags = collect($tagNames)->map(function ($name) {
            return KnowledgeBaseTag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        });

        for ($i = 1; $i <= 15; $i++) {
            $item = KnowledgeBaseItem::create([
                'category_id' => $categories->random()->id,
                'title' => 'Resource #'.$i.': '.fake()->sentence(3),
                'description' => fake()->sentence(12),
                'type' => 'link',
                'disk_path' => null,
                'link_url' => "https://example.com/resource-{$i}",
                'original_name' => null,
                'mime_type' => null,
                'size' => null,
                'uploaded_by' => $uploader->id,
            ]);

            $item->tags()->sync($tags->random(rand(1, 3))->pluck('id')->all());
        }
    }
}
