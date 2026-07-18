<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_has_full_crud_access(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $someone = User::factory()->create(['role' => UserRole::BusinessDevelopment]);

        $this->actingAs($superAdmin)->post(route('tasks.store'), [
            'title' => 'Prepare onboarding docs',
            'module' => 'internal_operations',
            'assigned_to' => $someone->id,
        ])->assertRedirect();

        $task = Task::firstWhere('title', 'Prepare onboarding docs');
        $this->assertNotNull($task);
        $this->assertSame('assigned', $task->status->value);
        $this->assertSame($superAdmin->id, $task->assigned_by);

        $this->actingAs($superAdmin)->put(route('tasks.update', $task), [
            'title' => $task->title,
            'priority' => 'high',
            'status' => 'in_progress',
        ])->assertRedirect(route('tasks.show', $task));

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress', 'priority' => 'high']);

        $this->actingAs($superAdmin)->delete(route('tasks.destroy', $task))->assertRedirect(route('tasks.index'));
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_manager_can_assign_a_task_to_a_direct_and_indirect_subordinate(): void
    {
        $director = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $manager = User::factory()->create(['role' => UserRole::BusinessDevelopment, 'reporting_manager_id' => $director->id]);
        $employee = User::factory()->create(['role' => UserRole::BusinessDevelopment, 'reporting_manager_id' => $manager->id]);

        // Direct: manager -> employee.
        $this->actingAs($manager)->post(route('tasks.store'), [
            'title' => 'Direct-report task',
            'module' => 'internal_operations',
            'assigned_to' => $employee->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('tasks', ['title' => 'Direct-report task', 'assigned_to' => $employee->id, 'assigned_by' => $manager->id]);

        // Indirect (2 levels): director -> employee.
        $this->actingAs($director)->post(route('tasks.store'), [
            'title' => 'Indirect-report task',
            'module' => 'internal_operations',
            'assigned_to' => $employee->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('tasks', ['title' => 'Indirect-report task', 'assigned_to' => $employee->id]);
    }

    public function test_manager_cannot_assign_a_task_outside_their_reporting_chain(): void
    {
        $managerA = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $strangerEmployee = User::factory()->create(['role' => UserRole::BusinessDevelopment]);

        $this->actingAs($managerA)->post(route('tasks.store'), [
            'title' => 'Should fail',
            'module' => 'internal_operations',
            'assigned_to' => $strangerEmployee->id,
        ])->assertSessionHasErrors('assigned_to');

        $this->assertDatabaseMissing('tasks', ['title' => 'Should fail']);
    }

    public function test_assignee_can_view_and_update_their_own_task(): void
    {
        $manager = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $employee = User::factory()->create(['role' => UserRole::BusinessDevelopment, 'reporting_manager_id' => $manager->id]);
        $task = Task::factory()->create(['assigned_to' => $employee->id, 'assigned_by' => $manager->id, 'created_by' => $manager->id]);

        $this->actingAs($employee)->get(route('tasks.show', $task))->assertOk();

        $this->actingAs($employee)->put(route('tasks.update', $task), [
            'title' => $task->title,
            'priority' => $task->priority->value,
            'status' => 'in_progress',
        ])->assertRedirect(route('tasks.show', $task));

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress']);
    }

    public function test_unrelated_employee_cannot_view_someone_elses_task(): void
    {
        $manager = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $employee = User::factory()->create(['role' => UserRole::BusinessDevelopment, 'reporting_manager_id' => $manager->id]);
        $stranger = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $task = Task::factory()->create(['assigned_to' => $employee->id, 'assigned_by' => $manager->id, 'created_by' => $manager->id]);

        $this->actingAs($stranger)->get(route('tasks.show', $task))->assertForbidden();
    }

    public function test_checklist_item_can_be_added_and_toggled(): void
    {
        $manager = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $task = Task::factory()->create(['created_by' => $manager->id, 'assigned_by' => $manager->id, 'assigned_to' => $manager->id]);

        $this->actingAs($manager)->post(route('tasks.checklist-items.store', $task), [
            'title' => 'Send welcome email',
        ])->assertRedirect();

        $item = $task->checklistItems()->firstWhere('title', 'Send welcome email');
        $this->assertNotNull($item);
        $this->assertFalse($item->is_completed);

        $this->actingAs($manager)->patch(route('tasks.checklist-items.update', [$task, $item]))->assertRedirect();

        $this->assertTrue($item->fresh()->is_completed);
        $this->assertSame($manager->id, $item->fresh()->completed_by);
    }

    public function test_comment_can_be_added_and_only_author_or_task_manager_can_delete_it(): void
    {
        $manager = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $employee = User::factory()->create(['role' => UserRole::BusinessDevelopment, 'reporting_manager_id' => $manager->id]);
        $stranger = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $task = Task::factory()->create(['assigned_to' => $employee->id, 'assigned_by' => $manager->id, 'created_by' => $manager->id]);

        $this->actingAs($employee)->post(route('tasks.comments.store', $task), [
            'comment' => 'Started working on this.',
        ])->assertRedirect();

        $comment = TaskComment::firstWhere('task_id', $task->id);
        $this->assertNotNull($comment);

        // A stranger (no view access to the task) cannot post or delete.
        $this->actingAs($stranger)->post(route('tasks.comments.store', $task), ['comment' => 'nope'])->assertForbidden();
        $this->actingAs($stranger)->delete(route('tasks.comments.destroy', [$task, $comment]))->assertForbidden();

        // The author can delete their own comment.
        $this->actingAs($employee)->delete(route('tasks.comments.destroy', [$task, $comment]))->assertRedirect();
        $this->assertSoftDeleted('task_comments', ['id' => $comment->id]);
    }

    public function test_task_index_query_count_does_not_grow_with_subordinate_count(): void
    {
        // Pin a single shared department: creating many bare User::factory()
        // rows would otherwise each default to a brand-new Department via
        // Department::factory()'s fake()->unique()->randomElement() over
        // only 6 possible names, exhausting that pool process-wide.
        $department = \App\Models\Department::factory()->create();

        $managerA = User::factory()->create(['role' => UserRole::BusinessDevelopment, 'department_id' => $department->id]);
        $employeeA = User::factory()->create(['role' => UserRole::BusinessDevelopment, 'department_id' => $department->id, 'reporting_manager_id' => $managerA->id]);
        Task::factory()->create(['assigned_to' => $employeeA->id, 'created_by' => $managerA->id]);

        $managerB = User::factory()->create(['role' => UserRole::BusinessDevelopment, 'department_id' => $department->id]);
        $subordinatesB = User::factory()->count(6)->create(['role' => UserRole::BusinessDevelopment, 'department_id' => $department->id, 'reporting_manager_id' => $managerB->id]);
        foreach ($subordinatesB as $sub) {
            Task::factory()->create(['assigned_to' => $sub->id, 'created_by' => $managerB->id]);
        }

        DB::enableQueryLog();
        $this->actingAs($managerA)->get(route('tasks.index'))->assertOk();
        $queryCountForOne = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->actingAs($managerB)->get(route('tasks.index'))->assertOk();
        $queryCountForSix = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queryCountForOne, $queryCountForSix);
    }
}
