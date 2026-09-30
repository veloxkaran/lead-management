<?php

namespace Tests\Feature;

use App\Enums\RequirementStatus;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RequirementCompletionTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_requirement_saved_as_completed_records_its_completion_time(): void
    {
        $this->travelTo(Carbon::parse('2026-09-10 15:00:00'));

        $requirement = Requirement::factory()->create(['status' => RequirementStatus::Completed]);

        $this->assertSame('2026-09-10 15:00:00', $requirement->completed_at->format('Y-m-d H:i:s'));
    }

    public function test_reopening_clears_it_and_completing_again_records_a_new_time(): void
    {
        $requirement = Requirement::factory()->create(['status' => RequirementStatus::Completed]);

        $requirement->update(['status' => RequirementStatus::InProgress]);
        $this->assertNull($requirement->completed_at);

        $this->travel(2)->hours();
        $requirement->update(['status' => RequirementStatus::Completed]);
        $this->assertTrue($requirement->completed_at->isSameMinute(now()));
    }

    public function test_solved_in_is_only_reported_for_completed_requirements(): void
    {
        $open = Requirement::factory()->create(['status' => RequirementStatus::Pending]);
        $done = Requirement::factory()->create(['status' => RequirementStatus::Completed, 'created_at' => '2026-09-01 09:00:00']);
        $done->forceFill(['completed_at' => '2026-09-02 11:30:00'])->saveQuietly();

        $this->assertNull($open->solvedInFormatted());
        $this->assertSame('1 days, 2 hour and 30 min', $done->fresh()->solvedInFormatted());
    }

    public function test_show_page_displays_completed_time_and_solved_in(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['status' => RequirementStatus::Completed, 'created_at' => '2026-09-01 09:00:00']);
        $requirement->forceFill(['completed_at' => '2026-09-02 11:30:00'])->saveQuietly();

        $this->actingAs($user)->get(route('requirements.show', $requirement))
            ->assertOk()
            ->assertSee('Completed')
            ->assertSee('Sep 02, 2026 11:30 AM')
            ->assertSee('Solved In')
            ->assertSee('1 days, 2 hour and 30 min');
    }

    public function test_list_shows_solved_in_for_completed_requirements(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['lead_id' => Lead::factory()->create()->id, 'status' => RequirementStatus::Completed, 'created_at' => '2026-09-01 09:00:00']);
        $requirement->forceFill(['completed_at' => '2026-09-01 10:15:00'])->saveQuietly();

        $this->actingAs($user)->get(route('requirements.index'))
            ->assertSee('Solved in 0 days, 1 hour and 15 min');
    }

    public function test_backfill_uses_the_logged_completion_time_or_falls_back_to_last_update(): void
    {
        $logged = Requirement::factory()->create(['status' => RequirementStatus::Pending]);
        $unlogged = Requirement::factory()->create(['status' => RequirementStatus::Pending]);
        // Simulate rows completed before completed_at was tracked on every path.
        DB::table('requirements')->where('id', $logged->id)->update(['status' => 'completed', 'completed_at' => null, 'updated_at' => '2026-08-05 09:00:00']);
        DB::table('requirements')->where('id', $unlogged->id)->update(['status' => 'completed', 'completed_at' => null, 'updated_at' => '2026-07-26 12:45:15']);
        DB::table('activity_log_entries')->insert([
            'user_id' => User::factory()->create()->id,
            'module' => 'requirements',
            'description' => 'Updated requirement',
            'subject_type' => Requirement::class,
            'subject_id' => $logged->id,
            'old_values' => json_encode(['status' => 'pending']),
            'new_values' => json_encode(['status' => 'completed']),
            'created_at' => '2026-08-05 08:23:55',
            'updated_at' => '2026-08-05 08:23:55',
        ]);

        (require database_path('migrations/2026_09_29_110000_backfill_requirement_completed_at.php'))->up();

        $this->assertSame('2026-08-05 08:23:55', $logged->fresh()->completed_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-26 12:45:15', $unlogged->fresh()->completed_at->format('Y-m-d H:i:s'));
    }
}
