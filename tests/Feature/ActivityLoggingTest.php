<?php

namespace Tests\Feature;

use App\Enums\ActivityModule;
use App\Enums\WhatsappMessageDirection;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\PolicyDocument;
use App\Models\Requirement;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_lead_logs_an_activity(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['created_by' => $user->id, 'company_name' => 'Acme Corp']);

        $this->assertDatabaseHas('activity_log_entries', [
            'module' => ActivityModule::Lead->value,
            'user_id' => $user->id,
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'description' => 'created a new lead: Acme Corp',
        ]);
    }

    public function test_creating_a_requirement_logs_an_activity_with_the_requirement_text_not_a_title_field(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['created_by' => $user->id, 'requirement' => 'Needs a custom integration']);

        $this->assertDatabaseHas('activity_log_entries', [
            'module' => ActivityModule::Requirement->value,
            'subject_type' => Requirement::class,
            'subject_id' => $requirement->id,
            'description' => 'raised a requirement: Needs a custom integration',
        ]);
    }

    public function test_outbound_whatsapp_message_logs_an_activity(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['whatsapp_number' => '15551234567', 'company_name' => 'Acme Corp']);

        $message = $lead->whatsappMessages()->create([
            'direction' => WhatsappMessageDirection::Outbound,
            'to_number' => $lead->whatsapp_number,
            'type' => 'text',
            'body' => 'Hello',
            'status' => 'queued',
            'sent_by' => $user->id,
        ]);

        $this->assertDatabaseHas('activity_log_entries', [
            'module' => ActivityModule::Whatsapp->value,
            'user_id' => $user->id,
            'subject_id' => $message->id,
            'description' => 'sent a WhatsApp message to Acme Corp',
        ]);
    }

    public function test_inbound_whatsapp_message_does_not_log_an_activity(): void
    {
        $lead = Lead::factory()->create(['whatsapp_number' => '15551234567']);

        $message = $lead->whatsappMessages()->create([
            'direction' => WhatsappMessageDirection::Inbound,
            'from_number' => $lead->whatsapp_number,
            'type' => 'text',
            'body' => 'Hi there',
            'status' => 'received',
        ]);

        $this->assertDatabaseMissing('activity_log_entries', [
            'subject_type' => WhatsappMessage::class,
            'subject_id' => $message->id,
        ]);
    }

    public function test_publishing_a_policy_document_version_logs_an_activity(): void
    {
        $user = User::factory()->create();
        $document = PolicyDocument::factory()->create(['title' => 'Fire Safety SOP']);

        $version = $document->versions()->create([
            'version' => '1.0',
            'content' => '<p>Body</p>',
            'effective_date' => now()->toDateString(),
            'published_at' => now(),
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('activity_log_entries', [
            'module' => ActivityModule::PolicyDocument->value,
            'user_id' => $user->id,
            'subject_id' => $version->id,
            'description' => 'published SOP: Fire Safety SOP (v1.0)',
        ]);
    }

    public function test_adding_a_lead_note_logs_an_activity(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['company_name' => 'Acme Corp']);
        $note = LeadNote::factory()->create(['lead_id' => $lead->id, 'author_id' => $user->id]);

        $this->assertDatabaseHas('activity_log_entries', [
            'module' => ActivityModule::Note->value,
            'user_id' => $user->id,
            'subject_type' => LeadNote::class,
            'subject_id' => $note->id,
            'description' => 'added a note on Acme Corp',
        ]);
    }
}
