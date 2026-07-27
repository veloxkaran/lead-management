<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_user_can_post_a_comment_on_a_requirement(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create();

        $this->actingAs($user)->post(route('requirements.comments.store', $requirement), [
            'comment' => 'Confirmed with the client, moving ahead.',
        ])->assertRedirect();

        $this->assertDatabaseHas('requirement_comments', [
            'requirement_id' => $requirement->id,
            'author_id' => $user->id,
            'comment' => 'Confirmed with the client, moving ahead.',
        ]);
    }

    public function test_multiple_users_can_comment_on_the_same_requirement(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $requirement = Requirement::factory()->create();

        $this->actingAs($userA)->post(route('requirements.comments.store', $requirement), ['comment' => 'First comment']);
        $this->actingAs($userB)->post(route('requirements.comments.store', $requirement), ['comment' => 'Second comment']);

        $this->assertCount(2, $requirement->fresh()->comments);
    }

    public function test_show_page_displays_created_by_and_created_at(): void
    {
        $creator = User::factory()->create(['name' => 'Jane Creator']);
        $requirement = Requirement::factory()->create(['created_by' => $creator->id]);

        $response = $this->actingAs($creator)->get(route('requirements.show', $requirement));

        $response->assertOk();
        $response->assertSee('Created By');
        $response->assertSee('Jane Creator');
        $response->assertSee($requirement->created_at->format('M d, Y'));
    }

    public function test_show_page_displays_the_comment_thread(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create();
        $requirement->comments()->create(['comment' => 'A visible comment', 'author_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('requirements.show', $requirement));

        $response->assertOk();
        $response->assertSee('Comments (1)');
        $response->assertSee('A visible comment');
    }

    public function test_anyone_who_can_view_the_requirement_can_reach_the_show_page(): void
    {
        $creator = User::factory()->create();
        $stranger = User::factory()->create();
        $requirement = Requirement::factory()->create(['created_by' => $creator->id]);

        $this->actingAs($stranger)->get(route('requirements.show', $requirement))->assertOk();
    }

    public function test_lead_page_links_requirements_to_their_show_page(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => $user->id, 'created_by' => $user->id]);
        $requirement = Requirement::factory()->create(['lead_id' => $lead->id]);

        $response = $this->actingAs($user)->get(route('leads.show', $lead));

        $response->assertOk();
        $response->assertSee(route('requirements.show', $requirement));
    }
}
