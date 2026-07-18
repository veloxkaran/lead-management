<?php

namespace Tests\Feature;

use App\Enums\ActivityModule;
use App\Enums\UserRole;
use App\Models\ActivityLogEntry;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_task_logs_exactly_one_activity_entry_with_no_diff(): void
    {
        $manager = User::factory()->create(['role' => UserRole::BusinessDevelopment]);

        $this->actingAs($manager)->post(route('tasks.store'), [
            'title' => 'New client kickoff',
            'module' => 'internal_operations',
        ])->assertRedirect();

        $task = Task::firstWhere('title', 'New client kickoff');

        $entries = ActivityLogEntry::where('module', ActivityModule::Task)
            ->where('subject_type', $task->getMorphClass())
            ->where('subject_id', $task->id)
            ->get();

        $this->assertCount(1, $entries);
        $this->assertNull($entries->first()->old_values);
        $this->assertNull($entries->first()->new_values);
        $this->assertSame($manager->id, $entries->first()->user_id);
    }

    public function test_updating_status_and_assignee_together_logs_one_entry_with_both_diffs_and_request_metadata(): void
    {
        $manager = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $employeeA = User::factory()->create(['role' => UserRole::BusinessDevelopment, 'reporting_manager_id' => $manager->id]);
        $employeeB = User::factory()->create(['role' => UserRole::BusinessDevelopment, 'reporting_manager_id' => $manager->id]);
        $task = Task::factory()->create([
            'created_by' => $manager->id,
            'assigned_by' => $manager->id,
            'assigned_to' => $employeeA->id,
            'status' => 'assigned',
        ]);

        $this->actingAs($manager)
            ->withHeaders(['User-Agent' => 'PHPUnit-Test-Agent'])
            ->put(route('tasks.update', $task), [
                'title' => $task->title,
                'priority' => $task->priority->value,
                'status' => 'in_progress',
                'assigned_to' => $employeeB->id,
            ])->assertRedirect(route('tasks.show', $task));

        $entries = ActivityLogEntry::where('module', ActivityModule::Task)
            ->where('subject_type', $task->getMorphClass())
            ->where('subject_id', $task->id)
            ->where('description', 'like', 'updated%')
            ->get();

        $this->assertCount(1, $entries);

        $entry = $entries->first();
        $this->assertSame('in_progress', $entry->new_values['status']);
        $this->assertSame('assigned', $entry->old_values['status']);
        $this->assertEquals($employeeB->id, $entry->new_values['assigned_to']);
        $this->assertEquals($employeeA->id, $entry->old_values['assigned_to']);
        $this->assertNotNull($entry->ip_address);
        $this->assertSame('PHPUnit-Test-Agent', $entry->user_agent);
    }

    public function test_update_with_no_actual_changes_logs_nothing_new(): void
    {
        $manager = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $task = Task::factory()->create(['created_by' => $manager->id, 'assigned_by' => $manager->id, 'assigned_to' => $manager->id]);

        $before = ActivityLogEntry::count();

        $this->actingAs($manager)->put(route('tasks.update', $task), [
            'title' => $task->title,
            'priority' => $task->priority->value,
            'status' => $task->status->value,
        ])->assertRedirect();

        $this->assertSame($before, ActivityLogEntry::count());
    }
}
