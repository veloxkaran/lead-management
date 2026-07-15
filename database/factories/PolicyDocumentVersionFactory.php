<?php

namespace Database\Factories;

use App\Models\PolicyDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PolicyDocumentVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'policy_document_id' => PolicyDocument::factory(),
            'version' => '1.0',
            'content' => '<p>'.fake()->paragraph().'</p>',
            'effective_date' => now()->toDateString(),
            'published_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
