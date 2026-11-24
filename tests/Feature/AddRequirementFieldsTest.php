<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\SystemModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Add Requirement" forms (create page, list modal, company modal)
 * share requirements/_add_fields.
 */
class AddRequirementFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'lead_id' => Lead::factory()->create()->id,
            'system_module_id' => SystemModule::factory()->create()->id,
            'requirement' => '<p>Needs SSO</p>',
            'priority' => 'medium',
        ];
    }

    public function test_an_editor_holding_only_blank_lines_or_spaces_is_rejected(): void
    {
        $user = User::factory()->create();

        foreach (['<p><br></p>', '<p><br></p><p><br></p>', '<p> </p>', '<p>&nbsp;</p>'] as $blank) {
            $this->actingAs($user)->post(route('requirements.store'), $this->payload(['requirement' => $blank]))
                ->assertSessionHasErrors('requirement');
        }

        $this->assertSame(0, Requirement::count());
    }

    public function test_editing_a_requirement_to_blank_is_rejected(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)->put(route('requirements.update', $requirement), [
            'system_module_id' => $requirement->system_module_id,
            'requirement' => '<p><br></p><p> </p>',
            'priority' => $requirement->priority->value,
            'status' => $requirement->status->value,
        ])->assertSessionHasErrors('requirement');
    }

    public function test_every_field_is_saved(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create();

        $this->actingAs($user)->post(route('requirements.store'), $this->payload([
            'title' => 'SSO',
            'priority' => 'high',
            'due_date' => '2026-12-31',
            'client_acknowledged_at' => '2026-11-24T10:30',
            'assigned_to' => $assignee->id,
        ]))->assertSessionHasNoErrors();

        $requirement = Requirement::sole();
        $this->assertSame('SSO', $requirement->title);
        $this->assertSame('high', $requirement->priority->value);
        $this->assertSame('2026-12-31', $requirement->due_date->toDateString());
        $this->assertSame('2026-11-24 10:30', $requirement->client_acknowledged_at->format('Y-m-d H:i'));
        $this->assertSame($assignee->id, $requirement->assigned_to);
        $this->assertSame($user->id, $requirement->created_by);
    }

    public function test_inactive_members_cannot_be_assigned_or_offered(): void
    {
        $user = User::factory()->create();
        $inactive = User::factory()->create(['name' => 'Former Member', 'status' => UserStatus::Inactive]);

        $this->actingAs($user)->post(route('requirements.store'), $this->payload(['assigned_to' => $inactive->id]))
            ->assertSessionHasErrors('assigned_to');

        $this->actingAs($user)->get(route('requirements.create'))
            ->assertOk()
            ->assertDontSee('Former Member');
    }

    public function test_the_create_page_offers_only_active_leads_like_the_list_modal(): void
    {
        $user = User::factory()->create();
        Lead::factory()->create(['company_name' => 'Live Co']);
        Lead::factory()->create(['company_name' => 'Archived Co', 'archived_at' => now()]);

        $this->actingAs($user)->get(route('requirements.create'))
            ->assertOk()
            ->assertSee('Live Co')
            ->assertDontSee('Archived Co');
    }

    public function test_the_add_modal_reopens_only_for_its_own_errors(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['created_by' => $user->id]);
        $reopen = "getElementById('addRequirementModal')).show()";

        // A failed status popup on the same page must not pop the add modal.
        $this->actingAs($user)->from(route('requirements.index'))
            ->patch(route('requirements.status.update', $requirement), ['status' => 'in_progress', 'note' => '']);
        $this->actingAs($user)->get(route('requirements.index'))->assertDontSee($reopen, false);

        $this->actingAs($user)->from(route('requirements.index'))
            ->post(route('requirements.store'), $this->payload(['requirement' => '<p><br></p>', '_form' => 'add_requirement']));
        $this->actingAs($user)->get(route('requirements.index'))->assertSee($reopen, false);
    }
}
