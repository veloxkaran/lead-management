<?php

namespace Tests\Feature;

use App\Enums\RawDataAssignmentAction;
use App\Enums\RawDataStatus;
use App\Models\RawData;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawDataAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_sets_assigned_to_by_and_at(): void
    {
        $actor = User::factory()->create();
        $assignee = User::factory()->create();
        $rawData = RawData::factory()->create();

        $this->travelTo(Carbon::create(2026, 8, 1, 9, 0));

        $this->actingAs($actor)->post(route('raw-data.assign', $rawData), [
            'assigned_to' => $assignee->id,
        ])->assertRedirect();

        $rawData->refresh();
        $this->assertSame($assignee->id, $rawData->assigned_to);
        $this->assertSame($actor->id, $rawData->assigned_by);
        $this->assertTrue($rawData->assigned_at->equalTo(Carbon::create(2026, 8, 1, 9, 0)));
    }

    public function test_assignment_deadline_is_48_hours_after_assigned_at(): void
    {
        $rawData = RawData::factory()->create(['assigned_at' => '2026-08-01 09:00:00']);

        $this->assertTrue($rawData->assignmentDeadline()->equalTo(Carbon::create(2026, 8, 3, 9, 0)));
    }

    public function test_unassigning_clears_assigned_by_and_at(): void
    {
        $actor = User::factory()->create();
        $assignee = User::factory()->create();
        $rawData = RawData::factory()->create([
            'assigned_to' => $assignee->id,
            'assigned_by' => $assignee->id,
            'assigned_at' => '2026-08-01 09:00:00',
        ]);

        $this->actingAs($actor)->post(route('raw-data.assign', $rawData), [
            'assigned_to' => '',
        ])->assertRedirect();

        $rawData->refresh();
        $this->assertNull($rawData->assigned_to);
        $this->assertNull($rawData->assigned_by);
        $this->assertNull($rawData->assigned_at);
    }

    public function test_a_finalized_entry_cannot_be_reassigned(): void
    {
        $actor = User::factory()->create();
        $assignee = User::factory()->create();
        $rawData = RawData::factory()->create(['status' => RawDataStatus::NotValid]);

        $this->actingAs($actor)->post(route('raw-data.assign', $rawData), [
            'assigned_to' => $assignee->id,
        ])->assertSessionHasErrors('status');

        $this->assertNull($rawData->fresh()->assigned_to);
    }

    public function test_index_page_shows_assigned_to_and_time_remaining_columns(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create(['name' => 'Alex Assignee']);
        RawData::factory()->create(['assigned_to' => $assignee->id, 'assigned_by' => $assignee->id, 'assigned_at' => '2026-08-01 09:00:00']);

        $response = $this->actingAs($user)->get(route('raw-data.index'));

        $response->assertOk();
        $response->assertSee('Assigned To');
        $response->assertSee('Time Remaining');
        $response->assertSee('Alex Assignee');
    }

    public function test_show_page_displays_assignment_details_and_assign_form(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create(['name' => 'Alex Assignee']);
        $rawData = RawData::factory()->create(['assigned_to' => $assignee->id, 'assigned_by' => $assignee->id, 'assigned_at' => '2026-08-01 09:00:00']);

        $response = $this->actingAs($user)->get(route('raw-data.show', $rawData));

        $response->assertOk();
        $response->assertSee('Assigned To');
        $response->assertSee('Alex Assignee');
        $response->assertSee('Time Remaining');
    }

    public function test_assigning_logs_an_assigned_entry(): void
    {
        $actor = User::factory()->create();
        $assignee = User::factory()->create();
        $rawData = RawData::factory()->create();

        $this->actingAs($actor)->post(route('raw-data.assign', $rawData), [
            'assigned_to' => $assignee->id,
        ]);

        $this->assertDatabaseHas('raw_data_assignment_logs', [
            'raw_data_id' => $rawData->id,
            'action' => RawDataAssignmentAction::Assigned->value,
            'user_id' => $assignee->id,
            'performed_by' => $actor->id,
        ]);
        $this->assertSame(1, $rawData->assignmentLogs()->count());
    }

    public function test_unassigning_logs_an_unassigned_entry(): void
    {
        $actor = User::factory()->create();
        $assignee = User::factory()->create();
        $rawData = RawData::factory()->create([
            'assigned_to' => $assignee->id,
            'assigned_by' => $assignee->id,
            'assigned_at' => '2026-08-01 09:00:00',
        ]);

        $this->actingAs($actor)->post(route('raw-data.assign', $rawData), [
            'assigned_to' => '',
        ]);

        $this->assertDatabaseHas('raw_data_assignment_logs', [
            'raw_data_id' => $rawData->id,
            'action' => RawDataAssignmentAction::Unassigned->value,
            'user_id' => $assignee->id,
            'performed_by' => $actor->id,
        ]);
    }

    public function test_reassigning_to_someone_else_logs_both_unassigned_and_assigned_entries(): void
    {
        $actor = User::factory()->create();
        $firstAssignee = User::factory()->create();
        $secondAssignee = User::factory()->create();
        $rawData = RawData::factory()->create([
            'assigned_to' => $firstAssignee->id,
            'assigned_by' => $firstAssignee->id,
            'assigned_at' => '2026-08-01 09:00:00',
        ]);

        $this->actingAs($actor)->post(route('raw-data.assign', $rawData), [
            'assigned_to' => $secondAssignee->id,
        ]);

        $this->assertDatabaseHas('raw_data_assignment_logs', [
            'raw_data_id' => $rawData->id,
            'action' => RawDataAssignmentAction::Unassigned->value,
            'user_id' => $firstAssignee->id,
        ]);
        $this->assertDatabaseHas('raw_data_assignment_logs', [
            'raw_data_id' => $rawData->id,
            'action' => RawDataAssignmentAction::Assigned->value,
            'user_id' => $secondAssignee->id,
        ]);
        $this->assertSame(2, $rawData->assignmentLogs()->count());
    }

    public function test_resubmitting_the_same_assignee_is_a_no_op(): void
    {
        $actor = User::factory()->create();
        $assignee = User::factory()->create();
        $rawData = RawData::factory()->create([
            'assigned_to' => $assignee->id,
            'assigned_by' => $assignee->id,
            'assigned_at' => '2026-08-01 09:00:00',
        ]);

        $this->travelTo(Carbon::create(2026, 8, 2, 10, 0));

        $this->actingAs($actor)->post(route('raw-data.assign', $rawData), [
            'assigned_to' => $assignee->id,
        ]);

        $rawData->refresh();
        $this->assertSame(0, $rawData->assignmentLogs()->count());
        $this->assertSame('2026-08-01 09:00:00', $rawData->assigned_at->format('Y-m-d H:i:s'));
    }

    public function test_show_page_displays_assignment_history(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create(['name' => 'Alex Assignee']);
        $rawData = RawData::factory()->create();

        $this->actingAs($user)->post(route('raw-data.assign', $rawData), [
            'assigned_to' => $assignee->id,
        ]);

        $response = $this->actingAs($user)->get(route('raw-data.show', $rawData));

        $response->assertOk();
        $response->assertSee('Assignment History');
        $response->assertSee('Assigned');
        $response->assertSee('Alex Assignee');
    }
}
