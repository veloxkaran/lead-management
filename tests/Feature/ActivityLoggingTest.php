<?php

namespace Tests\Feature;

use App\Enums\ActivityModule;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Requirement;
use App\Models\User;
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
