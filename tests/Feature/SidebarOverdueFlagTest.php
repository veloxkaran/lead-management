<?php

namespace Tests\Feature;

use App\Enums\FollowUpStatus;
use App\Enums\RawDataStatus;
use App\Enums\RequirementStatus;
use App\Enums\TaskStatus;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\RawData;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\User;
use App\Services\OverdueScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarOverdueFlagTest extends TestCase
{
    use RefreshDatabase;

    private function counts(User $user): array
    {
        return app(OverdueScheduleService::class)->countsFor($user);
    }

    public function test_no_flags_when_nothing_is_overdue(): void
    {
        $user = User::factory()->create();

        $this->assertSame([], $this->counts($user));
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('nav-overdue-flag', false);
    }

    public function test_overdue_task_blinks_the_tasks_menu_with_a_flag(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(2)->create(['assigned_to' => $user->id, 'due_date' => now()->subDays(2)]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('nav-overdue', false)
            ->assertSee('title="Tasks · 2 overdue"', false)
            ->assertSee('<span class="nav-overdue-flag">overdue</span>', false);
    }

    public function test_tasks_that_are_done_not_yet_due_or_someone_elses_dont_count(): void
    {
        $user = User::factory()->create();
        Task::factory()->create(['assigned_to' => $user->id, 'due_date' => now()->subDay(), 'status' => TaskStatus::Completed->value]);
        Task::factory()->create(['assigned_to' => $user->id, 'due_date' => now()->subDay(), 'status' => TaskStatus::Cancelled->value]);
        Task::factory()->create(['assigned_to' => $user->id, 'due_date' => now()->addDays(3)]);
        Task::factory()->create(['assigned_to' => $user->id, 'due_date' => null]);
        Task::factory()->create(['assigned_to' => User::factory()->create()->id, 'due_date' => now()->subDay()]);

        $this->assertArrayNotHasKey('tasks', $this->counts($user));
    }

    public function test_overdue_requirement_assigned_to_the_member_is_flagged(): void
    {
        $user = User::factory()->create();
        Requirement::factory()->create(['assigned_to' => $user->id, 'due_date' => now()->subDay(), 'status' => RequirementStatus::InProgress->value]);
        Requirement::factory()->create(['assigned_to' => $user->id, 'due_date' => now()->addWeek()]);

        $this->assertSame(1, $this->counts($user)['requirements'] ?? 0);
        $this->actingAs($user)->get(route('dashboard'))->assertSee('title="Requirements · 1 overdue"', false);
    }

    public function test_past_pending_follow_up_on_the_members_lead_is_flagged(): void
    {
        $user = User::factory()->create();
        $ownLead = Lead::factory()->create(['assigned_user_id' => $user->id]);

        FollowUp::factory()->create(['created_by' => $user->id, 'follow_up_date' => now()->subDay()->toDateString()]);
        FollowUp::factory()->create(['lead_id' => $ownLead->id, 'follow_up_date' => now()->subDays(3)->toDateString()]);
        FollowUp::factory()->create(['created_by' => $user->id, 'follow_up_date' => now()->subDay()->toDateString(), 'status' => FollowUpStatus::Completed->value]);
        FollowUp::factory()->create(['created_by' => $user->id, 'follow_up_date' => now()->addDay()->toDateString()]);
        FollowUp::factory()->create(['follow_up_date' => now()->subDay()->toDateString()]);

        $this->assertSame(2, $this->counts($user)['follow_ups'] ?? 0);
        $this->actingAs($user)->get(route('dashboard'))->assertSee('title="Follow Ups · 2 overdue"', false);
    }

    public function test_raw_data_past_its_response_window_is_flagged(): void
    {
        $user = User::factory()->create();
        $hours = RawData::ASSIGNMENT_RESPONSE_HOURS;

        RawData::factory()->create(['assigned_to' => $user->id, 'assigned_at' => now()->subHours($hours + 1)]);
        RawData::factory()->create(['assigned_to' => $user->id, 'assigned_at' => now()->subHours($hours - 1)]);
        RawData::factory()->create(['assigned_to' => $user->id, 'assigned_at' => now()->subHours($hours + 1), 'status' => RawDataStatus::ConvertedToLead->value]);

        $this->assertSame(1, $this->counts($user)['raw_data'] ?? 0);
    }

    public function test_no_flag_for_a_component_the_member_cannot_view(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['permissions' => ['tasks' => []]])->save();
        Task::factory()->create(['assigned_to' => $user->id, 'due_date' => now()->subDay()]);

        $this->assertArrayNotHasKey('tasks', $this->counts($user));
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('nav-overdue-flag', false);
    }
}
