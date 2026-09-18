<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_four_default_templates_exist_after_migrating(): void
    {
        $this->assertDatabaseCount('email_templates', 4);
        $this->assertDatabaseHas('email_templates', ['key' => 'requirement_created']);
        $this->assertDatabaseHas('email_templates', ['key' => 'requirement_status_changed']);
        $this->assertDatabaseHas('email_templates', ['key' => 'support_ticket_created']);
        $this->assertDatabaseHas('email_templates', ['key' => 'support_ticket_status_changed']);
    }

    public function test_a_non_super_admin_cannot_access_email_templates(): void
    {
        $user = User::factory()->create();
        $template = EmailTemplate::first();

        $this->actingAs($user)->get(route('email-templates.index'))->assertForbidden();
        $this->actingAs($user)->get(route('email-templates.edit', $template))->assertForbidden();
    }

    public function test_super_admin_can_view_and_update_a_template(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $template = EmailTemplate::where('key', 'requirement_created')->first();

        $this->actingAs($superAdmin)->get(route('email-templates.index'))->assertOk()->assertSee('Requirement Created');

        $response = $this->actingAs($superAdmin)->get(route('email-templates.edit', $template));
        $response->assertOk();
        $response->assertSee('{{company_name}}');

        $this->actingAs($superAdmin)->put(route('email-templates.update', $template), [
            'subject' => 'Updated subject for {{company_name}}',
            'body' => 'Updated body with {{contact_person}}.',
        ])->assertRedirect(route('email-templates.index'));

        $template->refresh();
        $this->assertSame('Updated subject for {{company_name}}', $template->subject);
        $this->assertSame($superAdmin->id, $template->updated_by);
    }

    public function test_preview_renders_the_template_merged_with_sample_data(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $template = EmailTemplate::where('key', 'requirement_status_changed')->first();

        $response = $this->actingAs($superAdmin)->get(route('email-templates.preview', $template));

        $response->assertOk();
        $response->assertSee('Pending');
        $response->assertSee('In Progress');
        $response->assertDontSee('{{old_status}}', false);
    }
}
