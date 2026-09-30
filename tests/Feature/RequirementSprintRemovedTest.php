<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Requirement;
use App\Models\SystemModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint was dropped from the requirement forms and lists. The column stays
 * (older requirements keep their value) but nothing new can set it.
 */
class RequirementSprintRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_edit_pages_have_no_sprint_field(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)->get(route('requirements.create'))->assertOk()->assertDontSee('name="sprint"', false);
        $this->actingAs($user)->get(route('requirements.edit', $requirement))->assertOk()->assertDontSee('name="sprint"', false);
    }

    public function test_list_and_company_page_have_no_sprint_column_or_field(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        Requirement::factory()->create(['lead_id' => $lead->id, 'sprint' => 'Sprint 44']);

        foreach ([route('requirements.index'), route('requirements.company', $lead)] as $url) {
            $response = $this->actingAs($user)->get($url);

            $response->assertOk();
            $response->assertDontSee('name="sprint"', false);
            $response->assertDontSee('<th>Sprint</th>', false);
            $response->assertDontSee('Sprint 44');
        }
    }

    public function test_a_submitted_sprint_is_ignored_on_create(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('requirements.store'), [
            'system_module_id' => SystemModule::factory()->create()->id,
            'lead_id' => Lead::factory()->create()->id,
            'requirement' => 'Needs a custom dashboard',
            'priority' => 'medium',
            'sprint' => 'Sprint 40',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull(Requirement::where('requirement', 'like', '%Needs a custom dashboard%')->first()->sprint);
    }

    public function test_updating_a_requirement_keeps_its_existing_sprint(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['created_by' => $user->id, 'sprint' => 'Sprint 42']);

        $this->actingAs($user)->put(route('requirements.update', $requirement), [
            'system_module_id' => $requirement->system_module_id,
            'requirement' => 'Updated text',
            'priority' => $requirement->priority->value,
            'status' => $requirement->status->value,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Sprint 42', $requirement->refresh()->sprint);
    }

    public function test_show_page_only_displays_sprint_for_older_requirements_that_have_one(): void
    {
        $user = User::factory()->create();
        $legacy = Requirement::factory()->create(['sprint' => 'Sprint 42']);
        $current = Requirement::factory()->create(['sprint' => null]);

        $this->actingAs($user)->get(route('requirements.show', $legacy))->assertSee('Sprint 42');
        $this->actingAs($user)->get(route('requirements.show', $current))->assertDontSee('Sprint');
    }
}
