<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Models\ImplementationRequest;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImplementationRequestAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_role_can_raise_an_implementation_request_and_it_is_logged_on_the_lead(): void
    {
        $finance = User::factory()->create(['role' => UserRole::Finance]);
        $lead = Lead::factory()->create();

        $this->actingAs($finance)->post(route('implementation-requests.store'), [
            'lead_id' => $lead->id,
            'title' => 'Onboard Acme Corp',
        ])->assertRedirect(route('implementation-requests.index'));

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'activity_type' => ActivityType::ImplementationRequest->value,
            'created_by' => $finance->id,
        ]);

        $activity = $lead->activities()->latest()->first();
        $this->assertStringContainsString('raised by', $activity->description);
        $this->assertStringContainsString($finance->name, $activity->description);
    }

    public function test_any_role_can_update_an_implementation_request_and_it_is_logged_on_the_lead(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $finance = User::factory()->create(['role' => UserRole::Finance]);
        $lead = Lead::factory()->create();
        $request = ImplementationRequest::factory()->create([
            'lead_id' => $lead->id,
            'requested_by' => $manager->id,
            'title' => 'Onboard Acme Corp',
        ]);

        $this->actingAs($finance)->put(route('implementation-requests.update', $request), [
            'title' => $request->title,
            'status' => 'in_progress',
        ])->assertRedirect(route('implementation-requests.index'));

        $activity = $lead->activities()->latest()->first();
        $this->assertNotNull($activity);
        $this->assertSame(ActivityType::ImplementationRequest, $activity->activity_type);
        $this->assertStringContainsString('updated by '.$finance->name, $activity->description);
        $this->assertStringContainsString('In Progress', $activity->description);
    }

    public function test_the_implementation_request_activity_type_is_not_offered_in_the_manual_log_activity_form(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => $user->id, 'created_by' => $user->id]);

        $response = $this->actingAs($user)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertViewHas('activityTypes', fn ($types) => ! in_array(ActivityType::ImplementationRequest, $types, true));
    }
}
