<?php

namespace Tests\Feature;

use App\Models\ActivityLogEntry;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementSprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_requirement_can_be_created_with_a_sprint(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($user)->post(route('requirements.store'), [
            'lead_id' => $lead->id,
            'requirement' => 'Needs a custom dashboard',
            'priority' => 'medium',
            'sprint' => 'Sprint 40',
        ])->assertRedirect();

        $requirement = Requirement::firstWhere('requirement', 'Needs a custom dashboard');

        $this->assertNotNull($requirement);
        $this->assertSame('Sprint 40', $requirement->sprint);
    }

    public function test_sprint_is_optional_when_creating(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($user)->post(route('requirements.store'), [
            'lead_id' => $lead->id,
            'requirement' => 'Unscheduled requirement',
            'priority' => 'low',
        ])->assertRedirect();

        $requirement = Requirement::firstWhere('requirement', 'Unscheduled requirement');

        $this->assertNotNull($requirement);
        $this->assertNull($requirement->sprint);
    }

    public function test_an_out_of_range_sprint_value_is_rejected(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($user)->post(route('requirements.store'), [
            'lead_id' => $lead->id,
            'requirement' => 'Should not save',
            'priority' => 'low',
            'sprint' => 'Sprint 99',
        ])->assertSessionHasErrors('sprint');

        $this->assertNull(Requirement::firstWhere('requirement', 'Should not save'));
    }

    public function test_sprint_can_be_updated_and_is_logged(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['created_by' => $user->id, 'sprint' => null]);

        $this->actingAs($user)->put(route('requirements.update', $requirement), [
            'requirement' => $requirement->requirement,
            'priority' => $requirement->priority->value,
            'status' => $requirement->status->value,
            'sprint' => 'Sprint 42',
        ])->assertRedirect();

        $requirement->refresh();
        $this->assertSame('Sprint 42', $requirement->sprint);

        $entry = ActivityLogEntry::where('subject_type', Requirement::class)
            ->where('subject_id', $requirement->id)
            ->whereNotNull('new_values')
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertArrayHasKey('sprint', $entry->new_values);
        $this->assertSame('Sprint 42', $entry->new_values['sprint']);
    }

    public function test_requirements_index_shows_sprint_column(): void
    {
        $user = User::factory()->create();
        Requirement::factory()->create(['sprint' => 'Sprint 44']);

        $response = $this->actingAs($user)->get(route('requirements.index'));

        $response->assertOk();
        $response->assertSee('Sprint');
        $response->assertSee('Sprint 44');
    }

    public function test_requirement_show_page_displays_sprint(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['sprint' => 'Sprint 44']);

        $response = $this->actingAs($user)->get(route('requirements.show', $requirement));

        $response->assertOk();
        $response->assertSee('Sprint 44');
    }

    public function test_create_page_offers_the_sprint_35_to_50_dropdown(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('requirements.create'));

        $response->assertOk();
        $response->assertSee('Sprint 35');
        $response->assertSee('Sprint 50');
        $response->assertDontSee('Sprint 34');
        $response->assertDontSee('Sprint 51');
    }
}
