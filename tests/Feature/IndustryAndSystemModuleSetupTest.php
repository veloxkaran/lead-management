<?php

namespace Tests\Feature;

use App\Models\Industry;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\RawData;
use App\Models\Requirement;
use App\Models\SystemModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndustryAndSystemModuleSetupTest extends TestCase
{
    use RefreshDatabase;

    // --- Administration: Industries -------------------------------------

    public function test_super_admin_can_create_rename_and_delete_an_industry(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('industries.store'), ['name' => 'Retail'])
            ->assertRedirect(route('industries.index'));
        $industry = Industry::firstWhere('name', 'Retail');
        $this->assertNotNull($industry);

        $this->actingAs($admin)->put(route('industries.update', $industry), ['name' => 'Retail & Wholesale'])
            ->assertRedirect(route('industries.index'));
        $this->assertSame('Retail & Wholesale', $industry->fresh()->name);

        $this->actingAs($admin)->delete(route('industries.destroy', $industry))
            ->assertRedirect(route('industries.index'));
        $this->assertModelMissing($industry);
    }

    public function test_industry_names_must_be_unique(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Industry::factory()->create(['name' => 'Retail']);

        $this->actingAs($admin)->post(route('industries.store'), ['name' => 'Retail'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Industry::count());
    }

    public function test_renaming_an_industry_carries_over_to_its_leads(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $industry = Industry::factory()->create(['name' => 'Fin']);
        $lead = Lead::factory()->create(['industry' => 'Fin']);
        $trashed = Lead::factory()->create(['industry' => 'Fin']);
        $trashed->delete();
        $other = Lead::factory()->create(['industry' => 'Retail']);

        $this->actingAs($admin)->put(route('industries.update', $industry), ['name' => 'Finance']);

        $this->assertSame('Finance', $lead->fresh()->industry);
        $this->assertSame('Finance', Lead::withTrashed()->find($trashed->id)->industry);
        $this->assertSame('Retail', $other->fresh()->industry);
    }

    public function test_an_industry_in_use_cannot_be_deleted(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $industry = Industry::factory()->create(['name' => 'Finance']);
        Lead::factory()->create(['industry' => 'Finance']);

        $this->actingAs($admin)->delete(route('industries.destroy', $industry))
            ->assertSessionHas('error');

        $this->assertModelExists($industry);
    }

    public function test_non_admins_cannot_manage_industries(): void
    {
        $user = User::factory()->create();
        $industry = Industry::factory()->create();

        $this->actingAs($user)->get(route('industries.index'))->assertForbidden();
        $this->actingAs($user)->post(route('industries.store'), ['name' => 'Retail'])->assertForbidden();
        $this->actingAs($user)->put(route('industries.update', $industry), ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->delete(route('industries.destroy', $industry))->assertForbidden();
    }

    public function test_industries_index_lists_industries_with_lead_counts(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Industry::factory()->create(['name' => 'Finance']);
        Lead::factory()->count(2)->create(['industry' => 'Finance']);

        $this->actingAs($admin)->get(route('industries.index'))
            ->assertOk()
            ->assertSee('Finance')
            ->assertSee('Still used by leads');
    }

    // --- Administration: System Modules ---------------------------------

    public function test_super_admin_can_create_rename_and_delete_a_module(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('system-modules.store'), ['name' => 'Payroll'])
            ->assertRedirect(route('system-modules.index'));
        $module = SystemModule::firstWhere('name', 'Payroll');
        $this->assertNotNull($module);

        $this->actingAs($admin)->put(route('system-modules.update', $module), ['name' => 'Payroll & HR'])
            ->assertRedirect(route('system-modules.index'));
        $this->assertSame('Payroll & HR', $module->fresh()->name);

        $this->actingAs($admin)->delete(route('system-modules.destroy', $module))
            ->assertRedirect(route('system-modules.index'));
        $this->assertModelMissing($module);
    }

    public function test_a_module_in_use_cannot_be_deleted(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $requirement = Requirement::factory()->create();

        $this->actingAs($admin)->delete(route('system-modules.destroy', $requirement->system_module_id))
            ->assertSessionHas('error');

        $this->assertNotNull($requirement->fresh()->systemModule);
    }

    public function test_non_admins_cannot_manage_modules(): void
    {
        $user = User::factory()->create();
        $module = SystemModule::factory()->create();

        $this->actingAs($user)->get(route('system-modules.index'))->assertForbidden();
        $this->actingAs($user)->post(route('system-modules.store'), ['name' => 'Payroll'])->assertForbidden();
        $this->actingAs($user)->put(route('system-modules.update', $module), ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->delete(route('system-modules.destroy', $module))->assertForbidden();
    }

    public function test_admin_setup_pages_render(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $industry = Industry::factory()->create(['name' => 'Finance']);
        $module = SystemModule::factory()->create(['name' => 'Attendance']);

        $this->actingAs($admin)->get(route('industries.create'))->assertOk();
        $this->actingAs($admin)->get(route('industries.edit', $industry))->assertOk()->assertSee('Finance');
        $this->actingAs($admin)->get(route('system-modules.index'))->assertOk()->assertSee('Attendance');
        $this->actingAs($admin)->get(route('system-modules.create'))->assertOk();
        $this->actingAs($admin)->get(route('system-modules.edit', $module))->assertOk()->assertSee('Attendance');
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee(route('industries.index'))
            ->assertSee(route('system-modules.index'));
    }

    public function test_other_lead_and_requirement_entry_points_offer_the_lists(): void
    {
        $user = User::factory()->create();
        Industry::factory()->create(['name' => 'Finance']);
        $module = SystemModule::factory()->create(['name' => 'Attendance']);
        $lead = Lead::factory()->create();
        $entry = RawData::factory()->create();

        $this->actingAs($user)->get(route('raw-data.show', $entry))
            ->assertOk()->assertSee('<option value="Finance"', false);
        $this->actingAs($user)->get(route('leads.bulk-upload.create'))
            ->assertOk()->assertSee('Finance');
        $this->actingAs($user)->get(route('requirements.company', $lead))
            ->assertOk()->assertSee('<option value="'.$module->id.'"', false);
    }

    public function test_forms_explain_when_no_options_are_set_up(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('leads.create'))
            ->assertOk()->assertSee('No industries have been set up yet');
        $this->actingAs($user)->get(route('requirements.create'))
            ->assertOk()->assertSee('No system modules have been set up yet');
    }

    // --- Leads must pick an industry from the list ----------------------

    public function test_lead_form_offers_the_configured_industries(): void
    {
        $user = User::factory()->create();
        Industry::factory()->create(['name' => 'Finance']);
        Industry::factory()->create(['name' => 'Healthcare']);

        $this->actingAs($user)->get(route('leads.create'))
            ->assertOk()
            ->assertSee('<option value="Finance"', false)
            ->assertSee('<option value="Healthcare"', false);
    }

    public function test_creating_a_lead_requires_an_industry(): void
    {
        $user = User::factory()->create();
        LeadStatus::factory()->create(['is_default' => true]);

        $this->actingAs($user)->post(route('leads.store'), [
            'company_name' => 'No Industry Co',
            'contact_person' => 'Jane Doe',
        ])->assertSessionHasErrors('industry');

        $this->assertDatabaseMissing('leads', ['company_name' => 'No Industry Co']);
    }

    public function test_creating_a_lead_rejects_an_industry_not_in_the_list(): void
    {
        $user = User::factory()->create();
        LeadStatus::factory()->create(['is_default' => true]);
        Industry::factory()->create(['name' => 'Finance']);

        $this->actingAs($user)->post(route('leads.store'), [
            'company_name' => 'Made Up Co',
            'contact_person' => 'Jane Doe',
            'industry' => 'Aerospace',
        ])->assertSessionHasErrors('industry');

        $this->assertDatabaseMissing('leads', ['company_name' => 'Made Up Co']);
    }

    public function test_a_lead_is_saved_with_the_chosen_industry(): void
    {
        $user = User::factory()->create();
        LeadStatus::factory()->create(['is_default' => true]);
        Industry::factory()->create(['name' => 'Finance']);

        $this->actingAs($user)->post(route('leads.store'), [
            'company_name' => 'Listed Co',
            'contact_person' => 'Jane Doe',
            'industry' => 'Finance',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leads', ['company_name' => 'Listed Co', 'industry' => 'Finance']);
    }

    public function test_editing_a_lead_with_an_unlisted_industry_must_pick_a_listed_one(): void
    {
        $user = User::factory()->create();
        Industry::factory()->create(['name' => 'Finance']);
        $lead = Lead::factory()->create(['industry' => 'Old Free Text']);

        $this->actingAs($user)->get(route('leads.edit', $lead))
            ->assertOk()
            ->assertSee('no longer in the list');

        $this->actingAs($user)->put(route('leads.update', $lead), [
            'company_name' => $lead->company_name,
            'contact_person' => $lead->contact_person,
            'industry' => 'Old Free Text',
        ])->assertSessionHasErrors('industry');

        $this->actingAs($user)->put(route('leads.update', $lead), [
            'company_name' => $lead->company_name,
            'contact_person' => $lead->contact_person,
            'industry' => 'Finance',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Finance', $lead->fresh()->industry);
    }

    public function test_converting_raw_data_to_a_lead_requires_a_listed_industry(): void
    {
        $user = User::factory()->create();
        LeadStatus::factory()->create(['is_default' => true]);
        Industry::factory()->create(['name' => 'Finance']);
        $entry = RawData::factory()->create(['contact_person' => 'Jane Doe']);

        $this->actingAs($user)->post(route('raw-data.convert', $entry), [
            'company_name' => 'Converted Co',
            'contact_person' => 'Jane Doe',
        ])->assertSessionHasErrors('industry');

        $this->actingAs($user)->post(route('raw-data.convert', $entry), [
            'company_name' => 'Converted Co',
            'contact_person' => 'Jane Doe',
            'industry' => 'Finance',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leads', ['company_name' => 'Converted Co', 'industry' => 'Finance']);
    }

    // --- Requirements must pick a module from the list ------------------

    public function test_requirement_forms_offer_the_configured_modules(): void
    {
        $user = User::factory()->create();
        $module = SystemModule::factory()->create(['name' => 'Attendance']);
        $lead = Lead::factory()->create();

        $this->actingAs($user)->get(route('requirements.create'))
            ->assertOk()->assertSee('Attendance');
        $this->actingAs($user)->get(route('requirements.index'))
            ->assertOk()->assertSee('<option value="'.$module->id.'"', false);
        $this->actingAs($user)->get(route('leads.show', $lead))
            ->assertOk()->assertSee('<option value="'.$module->id.'"', false);
    }

    public function test_creating_a_requirement_requires_a_module(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($user)->post(route('leads.requirements.store', $lead), [
            'requirement' => 'Needs a module',
            'priority' => 'medium',
        ])->assertSessionHasErrors('system_module_id');

        $this->actingAs($user)->post(route('leads.requirements.store', $lead), [
            'requirement' => 'Unknown module',
            'priority' => 'medium',
            'system_module_id' => 999,
        ])->assertSessionHasErrors('system_module_id');

        $this->assertSame(0, Requirement::count());
    }

    public function test_a_requirement_is_saved_with_the_chosen_module_and_shows_it(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();
        $module = SystemModule::factory()->create(['name' => 'Attendance']);

        $this->actingAs($user)->post(route('requirements.store'), [
            'lead_id' => $lead->id,
            'system_module_id' => $module->id,
            'requirement' => 'Shift rosters',
            'priority' => 'medium',
        ])->assertSessionHasNoErrors();

        $requirement = Requirement::firstOrFail();
        $this->assertSame($module->id, $requirement->system_module_id);

        $this->actingAs($user)->get(route('requirements.show', $requirement))->assertSee('Attendance');
        $this->actingAs($user)->get(route('requirements.index'))->assertSee('Attendance');
    }

    public function test_updating_a_requirement_requires_a_module_and_logs_the_change_by_name(): void
    {
        $user = User::factory()->create();
        $from = SystemModule::factory()->create(['name' => 'Attendance']);
        $to = SystemModule::factory()->create(['name' => 'Payroll']);
        $requirement = Requirement::factory()->create(['created_by' => $user->id, 'system_module_id' => $from->id]);

        $payload = [
            'requirement' => $requirement->requirement,
            'priority' => $requirement->priority->value,
            'status' => $requirement->status->value,
        ];

        $this->actingAs($user)->put(route('requirements.update', $requirement), $payload)
            ->assertSessionHasErrors('system_module_id');

        $this->actingAs($user)->put(route('requirements.update', $requirement), [...$payload, 'system_module_id' => $to->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($to->id, $requirement->fresh()->system_module_id);

        $this->actingAs($user)->get(route('requirements.edit', $requirement))
            ->assertOk()
            ->assertSeeInOrder(['Module:', 'Attendance', 'Payroll']);
    }
}
