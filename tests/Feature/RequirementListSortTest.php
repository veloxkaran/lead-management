<?php

namespace Tests\Feature;

use App\Enums\RequirementPriority;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementListSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_sorts_each_companys_requirements_by_priority_then_oldest_created_first(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $lowNew = Requirement::factory()->create(['lead_id' => $lead->id, 'priority' => RequirementPriority::Low]);
        $lowNew->forceFill(['created_at' => now()])->save();

        $urgentOld = Requirement::factory()->create(['lead_id' => $lead->id, 'priority' => RequirementPriority::Urgent]);
        $urgentOld->forceFill(['created_at' => now()->subDays(3)])->save();

        $urgentNew = Requirement::factory()->create(['lead_id' => $lead->id, 'priority' => RequirementPriority::Urgent]);
        $urgentNew->forceFill(['created_at' => now()->subDays(1)])->save();

        $mediumMid = Requirement::factory()->create(['lead_id' => $lead->id, 'priority' => RequirementPriority::Medium]);
        $mediumMid->forceFill(['created_at' => now()->subDays(2)])->save();

        $response = $this->actingAs($user)->get(route('requirements.index'));

        $response->assertOk();

        $companies = $response->viewData('companies');
        $ids = $companies->firstWhere('id', $lead->id)->requirements->pluck('id')->all();

        $this->assertSame([
            $urgentOld->id,
            $urgentNew->id,
            $mediumMid->id,
            $lowNew->id,
        ], $ids);
    }
}
