<?php

namespace Tests\Feature;

use App\Enums\RequirementPriority;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementListSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_sorts_by_priority_then_oldest_created_first_by_default(): void
    {
        $user = User::factory()->create();

        $lowNew = Requirement::factory()->create(['priority' => RequirementPriority::Low]);
        $lowNew->forceFill(['created_at' => now()])->save();

        $urgentOld = Requirement::factory()->create(['priority' => RequirementPriority::Urgent]);
        $urgentOld->forceFill(['created_at' => now()->subDays(3)])->save();

        $urgentNew = Requirement::factory()->create(['priority' => RequirementPriority::Urgent]);
        $urgentNew->forceFill(['created_at' => now()->subDays(1)])->save();

        $mediumMid = Requirement::factory()->create(['priority' => RequirementPriority::Medium]);
        $mediumMid->forceFill(['created_at' => now()->subDays(2)])->save();

        $response = $this->actingAs($user)->get(route('requirements.index'));

        $response->assertOk();

        $ids = $response->viewData('requirements')->pluck('id')->all();

        $this->assertSame([
            $urgentOld->id,
            $urgentNew->id,
            $mediumMid->id,
            $lowNew->id,
        ], $ids);
    }
}
