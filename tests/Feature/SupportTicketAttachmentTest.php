<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupportTicketAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_documents_can_be_attached_when_raising_a_ticket(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('support-tickets.store'), [
            'subject' => 'Client cannot log in',
            'priority' => 'high',
            'attachments' => [
                UploadedFile::fake()->create('screenshot.png', 100),
                UploadedFile::fake()->create('error-log.txt', 50),
            ],
        ])->assertRedirect();

        $ticket = SupportTicket::firstWhere('subject', 'Client cannot log in');

        $this->assertNotNull($ticket);
        $this->assertCount(2, $ticket->attachments);
        Storage::disk('public')->assertExists($ticket->attachments->first()->disk_path);
    }

    public function test_more_documents_can_be_added_to_an_existing_ticket_on_update(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();
        $ticket->attachments()->create([
            'disk_path' => 'support-tickets/1/existing.pdf',
            'original_name' => 'existing.pdf',
        ]);

        $this->actingAs($user)->put(route('support-tickets.update', $ticket), [
            'subject' => $ticket->subject,
            'priority' => $ticket->priority->value,
            'status' => $ticket->status->value,
            'attachments' => [UploadedFile::fake()->create('follow-up.docx', 80)],
        ])->assertRedirect();

        $this->assertCount(2, $ticket->fresh()->attachments);
    }

    public function test_a_document_can_be_downloaded_by_anyone_who_can_view_the_ticket(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();
        $attachment = $ticket->attachments()->create([
            'disk_path' => 'support-tickets/'.$ticket->id.'/test.pdf',
            'original_name' => 'test.pdf',
        ]);
        Storage::disk('public')->put($attachment->disk_path, 'fake contents');

        $this->actingAs($user)->get(route('support-ticket-attachments.download', $attachment))->assertOk();
    }

    public function test_a_document_can_be_previewed_inline_by_anyone_who_can_view_the_ticket(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();
        $attachment = $ticket->attachments()->create([
            'disk_path' => 'support-tickets/'.$ticket->id.'/test.pdf',
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
        ]);
        Storage::disk('public')->put($attachment->disk_path, 'fake contents');

        $response = $this->actingAs($user)->get(route('support-ticket-attachments.preview', $attachment));

        $response->assertOk();
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
    }

    public function test_show_page_links_each_document_to_the_preview_route(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();
        $attachment = $ticket->attachments()->create([
            'disk_path' => 'support-tickets/'.$ticket->id.'/test.pdf',
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($user)->get(route('support-tickets.show', $ticket));

        $response->assertOk();
        // Matches the same escaping the @js() Blade directive applies, since a raw
        // URL comparison would miss the JSON-escaped slashes in the rendered HTML.
        $response->assertSee(\Illuminate\Support\Js::from(route('support-ticket-attachments.preview', $attachment))->toHtml(), false);
    }

    public function test_documents_can_be_attached_when_raising_a_ticket_from_the_lead_page(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($user)->post(route('leads.support-tickets.store', $lead), [
            'subject' => 'Onboarding issue',
            'priority' => 'medium',
            'attachments' => [UploadedFile::fake()->create('doc.pdf', 60)],
        ])->assertRedirect();

        $ticket = SupportTicket::firstWhere('subject', 'Onboarding issue');
        $this->assertCount(1, $ticket->attachments);
    }
}
