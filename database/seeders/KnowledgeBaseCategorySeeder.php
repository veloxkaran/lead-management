<?php

namespace Database\Seeders;

use App\Models\KnowledgeBaseCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KnowledgeBaseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['Documents', 'PDF', 'Images', 'Videos', 'Links'];

        foreach ($categories as $name) {
            KnowledgeBaseCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}
