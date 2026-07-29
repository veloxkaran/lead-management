<?php

namespace Tests\Feature;

use App\Models\ActivityLogEntry;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementAdoptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_adopted_by_and_adopted_at_can_be_set_on_update_and_is_logged(): void
    {
        $user = User::factory()->create();
        $adopter = User::factory()->create();
        $requirement = Requirement::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)->put(route('requirements.update', $requirement), [
            'requirement' => $requirement->requirement,
            'priority' => $requirement->priority->value,
            'status' => $requirement->status->value,
            'adopted_by' => $adopter->id,
            'adopted_at' => '2026-08-10T14:30',
        ])->assertRedirect();

        $requirement->refresh();
        $this->assertTrue($requirement->isAdopted());
        $this->assertSame($adopter->id, $requirement->adopted_by);
        $this->assertNotNull($requirement->adopted_at);

        $entry = ActivityLogEntry::where('subject_type', Requirement::class)
            ->where('subject_id', $requirement->id)
            ->whereNotNull('new_values')
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertArrayHasKey('adopted_by', $entry->new_values);
        $this->assertArrayHasKey('adopted_at', $entry->new_values);
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
