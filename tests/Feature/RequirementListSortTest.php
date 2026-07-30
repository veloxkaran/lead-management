<?php

namespace Tests\Feature;

use App\Enums\RequirementStatus;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementListSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_sorts_by_status_then_oldest_created_first_by_default(): void
    {
        $user = User::factory()->create();

        $completedNew = Requirement::factory()->create(['status' => RequirementStatus::Completed]);
        $completedNew->forceFill(['created_at' => now()])->save();

        $pendingOld = Requirement::factory()->create(['status' => RequirementStatus::Pending]);
        $pendingOld->forceFill(['created_at' => now()->subDays(3)])->save();

        $pendingNew = Requirement::factory()->create(['status' => RequirementStatus::Pending]);
        $pendingNew->forceFill(['created_at' => now()->subDays(1)])->save();

        $onHoldMid = Requirement::factory()->create(['status' => RequirementStatus::OnHold]);
        $onHoldMid->forceFill(['created_at' => now()->subDays(2)])->save();

        $response = $this->actingAs($user)->get(route('requirements.index'));

        $response->assertOk();

        $ids = $response->viewData('requirements')->pluck('id')->all();

        $this->assertSame([
            $pendingOld->id,
            $pendingNew->id,
            $onHoldMid->id,
            $completedNew->id,
        ], $ids);
    }
}
