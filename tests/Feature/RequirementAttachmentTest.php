<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequirementAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_files_can_be_attached_when_creating_a_requirement(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($user)->post(route('requirements.store'), [
            'lead_id' => $lead->id,
            'requirement' => 'Needs a custom onboarding flow',
            'priority' => 'high',
            'attachments' => [
                UploadedFile::fake()->create('screenshot.png', 100, 'image/png'),
                UploadedFile::fake()->create('spec.docx', 50),
            ],
        ])->assertRedirect();

        $requirement = Requirement::firstWhere('requirement', 'Needs a custom onboarding flow');

        $this->assertNotNull($requirement);
        $this->assertCount(2, $requirement->attachments);
        Storage::disk('public')->assertExists($requirement->attachments->first()->disk_path);
    }

    public function test_only_accepted_file_types_are_allowed(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($user)->post(route('requirements.store'), [
            'lead_id' => $lead->id,
            'requirement' => 'Needs a custom export',
            'priority' => 'medium',
            'attachments' => [UploadedFile::fake()->create('malware.exe', 10)],
        ])->assertSessionHasErrors('attachments.0');
    }

    public function test_more_files_can_be_added_to_an_existing_requirement_on_update(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['created_by' => $user->id]);
        $requirement->attachments()->create([
            'disk_path' => 'requirements/1/existing.pdf',
            'original_name' => 'existing.pdf',
        ]);

        $this->actingAs($user)->put(route('requirements.update', $requirement), [
            'requirement' => $requirement->requirement,
            'priority' => $requirement->priority->value,
            'status' => $requirement->status->value,
            'attachments' => [UploadedFile::fake()->create('follow-up.xlsx', 80)],
        ])->assertRedirect();

        $this->assertCount(2, $requirement->fresh()->attachments);
    }

    public function test_a_file_can_be_downloaded_by_anyone_who_can_view_the_requirement(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $requirement = Requirement::factory()->create();
        $attachment = $requirement->attachments()->create([
            'disk_path' => 'requirements/'.$requirement->id.'/test.pdf',
            'original_name' => 'test.pdf',
        ]);
        Storage::disk('public')->put($attachment->disk_path, 'fake contents');

        $this->actingAs($user)->get(route('requirement-attachments.download', $attachment))->assertOk();
    }

    public function test_a_file_can_be_previewed_inline_by_anyone_who_can_view_the_requirement(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $requirement = Requirement::factory()->create();
        $attachment = $requirement->attachments()->create([
            'disk_path' => 'requirements/'.$requirement->id.'/test.pdf',
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
        ]);
        Storage::disk('public')->put($attachment->disk_path, 'fake contents');

        $response = $this->actingAs($user)->get(route('requirement-attachments.preview', $attachment));

        $response->assertOk();
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
    }

    public function test_show_page_links_each_file_to_the_preview_route(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $requirement = Requirement::factory()->create();
        $attachment = $requirement->attachments()->create([
            'disk_path' => 'requirements/'.$requirement->id.'/test.pdf',
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($user)->get(route('requirements.show', $requirement));

        $response->assertOk();
        // Matches the same escaping the @js() Blade directive applies, since a raw
        // URL comparison would miss the JSON-escaped slashes in the rendered HTML.
        $response->assertSee(\Illuminate\Support\Js::from(route('requirement-attachments.preview', $attachment))->toHtml(), false);
    }

    public function test_files_can_be_attached_when_creating_a_requirement_from_the_lead_page(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($user)->post(route('leads.requirements.store', $lead), [
            'requirement' => 'Needs a data migration',
            'priority' => 'medium',
            'attachments' => [UploadedFile::fake()->create('doc.csv', 60)],
        ])->assertRedirect();

        $requirement = Requirement::firstWhere('requirement', 'Needs a data migration');
        $this->assertCount(1, $requirement->attachments);
    }
}
