<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementTitleAndRichTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_requirement_can_be_created_with_a_title(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($user)->post(route('requirements.store'), [
            'lead_id' => $lead->id,
            'title' => 'Needs SSO support',
            'requirement' => 'Full requirement details go here.',
            'priority' => 'medium',
        ])->assertRedirect();

        $requirement = Requirement::where('title', 'Needs SSO support')->first();

        $this->assertNotNull($requirement);
    }

    public function test_title_is_optional_when_creating(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($user)->post(route('requirements.store'), [
            'lead_id' => $lead->id,
            'requirement' => 'No title on this one.',
            'priority' => 'medium',
        ])->assertRedirect();

        $requirement = Requirement::where('requirement', 'like', '%No title on this one%')->first();

        $this->assertNotNull($requirement);
        $this->assertNull($requirement->title);
    }

    public function test_dangerous_html_in_the_requirement_body_is_stripped_on_save(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($user)->post(route('requirements.store'), [
            'lead_id' => $lead->id,
            'requirement' => '<p>Bold text: <strong>urgent</strong></p><script>alert(1)</script>',
            'priority' => 'high',
        ])->assertRedirect();

        $requirement = Requirement::where('requirement', 'like', '%urgent%')->first();

        $this->assertNotNull($requirement);
        $this->assertStringContainsString('<strong>urgent</strong>', $requirement->requirement);
        $this->assertStringNotContainsString('<script', $requirement->requirement);
    }

    public function test_show_page_renders_the_sanitized_requirement_body_as_html(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create([
            'title' => 'Formatted requirement',
            'requirement' => '<p>Needs <strong>bold</strong> formatting.</p>',
        ]);

        $response = $this->actingAs($user)->get(route('requirements.show', $requirement));

        $response->assertOk();
        $response->assertSee('Formatted requirement');
        $response->assertSee('<strong>bold</strong>', false);
    }

    public function test_updating_a_requirement_sanitizes_the_new_body(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)->put(route('requirements.update', $requirement), [
            'title' => 'Updated title',
            'requirement' => '<p>Updated</p><img src=x onerror=alert(1)>',
            'priority' => $requirement->priority->value,
            'status' => $requirement->status->value,
        ])->assertRedirect();

        $requirement->refresh();

        $this->assertSame('Updated title', $requirement->title);
        $this->assertStringNotContainsString('onerror', $requirement->requirement);
    }
}
