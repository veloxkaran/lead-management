<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_success_can_schedule_and_update_a_training(): void
    {
        $cs = User::factory()->create(['role' => UserRole::CustomerSuccess]);
        $lead = Lead::factory()->create();

        $this->actingAs($cs)->post(route('trainings.store'), [
            'lead_id' => $lead->id,
            'training_date' => now()->addWeek()->toDateString(),
            'trainer_name' => 'Jane Doe',
            'attendees_count' => 12,
        ])->assertRedirect(route('leads.show', $lead));

        $training = Training::firstWhere('lead_id', $lead->id);
        $this->assertNotNull($training);
        $this->assertSame('not_scheduled', $training->status->value);

        $this->actingAs($cs)->put(route('trainings.update', $training), [
            'status' => 'completed',
            'training_date' => $training->training_date->toDateString(),
            'completion_percentage' => 100,
            'feedback' => 'Great session.',
        ])->assertRedirect(route('leads.show', $lead));

        $this->assertDatabaseHas('trainings', [
            'id' => $training->id,
            'status' => 'completed',
            'completion_percentage' => 100,
            'feedback' => 'Great session.',
        ]);
    }

    public function test_business_development_cannot_create_or_edit_trainings(): void
    {
        $bde = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $lead = Lead::factory()->create();

        $this->actingAs($bde)->get(route('trainings.create'))->assertForbidden();

        $training = Training::factory()->create(['lead_id' => $lead->id]);
        $this->actingAs($bde)->get(route('trainings.edit', $training))->assertForbidden();
    }

    public function test_lead_scoped_history_page_lists_only_that_leads_trainings(): void
    {
        $cs = User::factory()->create(['role' => UserRole::CustomerSuccess]);
        $lead = Lead::factory()->create();
        $otherLead = Lead::factory()->create();

        Training::factory()->count(2)->create(['lead_id' => $lead->id]);
        Training::factory()->create(['lead_id' => $otherLead->id]);

        $response = $this->actingAs($cs)->get(route('leads.trainings.index', $lead));

        $response->assertOk();
        $response->assertViewHas('trainings', fn ($trainings) => $trainings->total() === 2);
    }
}
