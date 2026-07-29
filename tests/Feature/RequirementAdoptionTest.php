<?php

namespace Tests\Feature;

use App\Models\ActivityLogEntry;
use App\Models\Requirement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementAdoptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_adopted_by_on_update_auto_sets_adopted_at_and_is_logged(): void
    {
        $user = User::factory()->create();
        $adopter = User::factory()->create();
        $requirement = Requirement::factory()->create(['created_by' => $user->id]);

        $this->travelTo(Carbon::create(2026, 8, 10, 14, 30));

        $this->actingAs($user)->put(route('requirements.update', $requirement), [
            'requirement' => $requirement->requirement,
            'priority' => $requirement->priority->value,
            'status' => $requirement->status->value,
            'adopted_by' => $adopter->id,
        ])->assertRedirect();

        $requirement->refresh();
        $this->assertTrue($requirement->isAdopted());
        $this->assertSame($adopter->id, $requirement->adopted_by);
        $this->assertTrue($requirement->adopted_at->equalTo(Carbon::create(2026, 8, 10, 14, 30)));

        $entry = ActivityLogEntry::where('subject_type', Requirement::class)
            ->where('subject_id', $requirement->id)
            ->whereNotNull('new_values')
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertArrayHasKey('adopted_by', $entry->new_values);
        $this->assertArrayHasKey('adopted_at', $entry->new_values);
    }

    public function test_clearing_adopted_by_on_update_clears_adopted_at(): void
    {
        $user = User::factory()->create();
        $adopter = User::factory()->create();
        $requirement = Requirement::factory()->create([
            'created_by' => $user->id,
            'adopted_by' => $adopter->id,
            'adopted_at' => '2026-08-10 14:30:00',
        ]);

        $this->actingAs($user)->put(route('requirements.update', $requirement), [
            'requirement' => $requirement->requirement,
            'priority' => $requirement->priority->value,
            'status' => $requirement->status->value,
            'adopted_by' => '',
        ])->assertRedirect();

        $requirement->refresh();
        $this->assertFalse($requirement->isAdopted());
        $this->assertNull($requirement->adopted_at);
    }

    public function test_adopted_at_does_not_change_when_adopted_by_is_left_unchanged(): void
    {
        $user = User::factory()->create();
        $adopter = User::factory()->create();
        $requirement = Requirement::factory()->create([
            'created_by' => $user->id,
            'adopted_by' => $adopter->id,
            'adopted_at' => '2026-08-10 14:30:00',
        ]);

        $this->actingAs($user)->put(route('requirements.update', $requirement), [
            'requirement' => $requirement->requirement,
            'priority' => $requirement->priority->value,
            'status' => $requirement->status->value,
            'adopted_by' => $adopter->id,
        ])->assertRedirect();

        $this->assertSame('2026-08-10 14:30:00', $requirement->fresh()->adopted_at->format('Y-m-d H:i:s'));
    }

    public function test_requirement_is_not_adopted_by_default(): void
    {
        $requirement = Requirement::factory()->create();

        $this->assertFalse($requirement->isAdopted());
    }

    public function test_requirements_index_shows_adopted_by_and_adopted_at_columns(): void
    {
        $user = User::factory()->create();
        $adopter = User::factory()->create(['name' => 'Alex Adopter']);
        Requirement::factory()->create(['adopted_by' => $adopter->id, 'adopted_at' => '2026-08-10 14:30:00']);

        $response = $this->actingAs($user)->get(route('requirements.index'));

        $response->assertOk();
        $response->assertSee('Adopted By');
        $response->assertSee('Adopted At');
        $response->assertSee('Alex Adopter');
        $response->assertSee('Aug 10, 2026');
    }

    public function test_requirement_show_page_displays_adoption_details(): void
    {
        $user = User::factory()->create();
        $adopter = User::factory()->create(['name' => 'Alex Adopter']);
        $requirement = Requirement::factory()->create(['adopted_by' => $adopter->id, 'adopted_at' => '2026-08-10 14:30:00']);

        $response = $this->actingAs($user)->get(route('requirements.show', $requirement));

        $response->assertOk();
        $response->assertSee('Adopted By');
        $response->assertSee('Alex Adopter');
        $response->assertSee('Adopted At');
    }
}
