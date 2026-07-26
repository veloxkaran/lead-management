<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agenda;
use App\Models\User;
use App\Notifications\AgendaCommentNotification;
use App\Notifications\AgendaCreatedNotification;
use App\Notifications\AgendaStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MeetingRoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_can_view_the_meeting_room(): void
    {
        $finance = User::factory()->create(['role' => UserRole::Finance]);

        $this->actingAs($finance)->get(route('meeting-room.index'))->assertOk();
    }

    public function test_any_role_can_raise_an_agenda_and_it_notifies_other_users(): void
    {
        Notification::fake();

        $bde = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $other = User::factory()->create(['role' => UserRole::Finance]);

        $this->actingAs($bde)->post(route('meeting-room.store'), [
            'title' => 'Q3 pipeline review',
            'description' => 'Discuss the Q3 pipeline numbers.',
        ])->assertRedirect();

        $agenda = Agenda::firstWhere('title', 'Q3 pipeline review');

        $this->assertNotNull($agenda);
        $this->assertSame('pending', $agenda->status->value);
        $this->assertSame($bde->id, $agenda->created_by);

        Notification::assertSentTo($other, AgendaCreatedNotification::class);
        Notification::assertNotSentTo($bde, AgendaCreatedNotification::class);
    }

    public function test_only_the_creator_can_close_or_dismiss_an_agenda(): void
    {
        $creator = User::factory()->create();
        $other = User::factory()->create();
        $agenda = Agenda::factory()->create(['created_by' => $creator->id]);

        $this->actingAs($other)->patchJson(route('meeting-room.status.update', $agenda), ['status' => 'closed'])
            ->assertForbidden();

        $this->actingAs($creator)->patchJson(route('meeting-room.status.update', $agenda), ['status' => 'closed'])
            ->assertOk();

        $this->assertSame('closed', $agenda->fresh()->status->value);
    }

    public function test_a_finalized_agenda_can_never_transition_again(): void
    {
        $creator = User::factory()->create();
        $agenda = Agenda::factory()->create(['created_by' => $creator->id, 'status' => 'closed']);

        $this->actingAs($creator)->patchJson(route('meeting-room.status.update', $agenda), ['status' => 'dismissed'])
            ->assertStatus(422);

        $this->assertSame('closed', $agenda->fresh()->status->value);
    }

    public function test_any_user_can_comment_while_pending_and_all_other_users_are_notified(): void
    {
        Notification::fake();

        $creator = User::factory()->create();
        $commenter = User::factory()->create();
        $bystander = User::factory()->create();
        $agenda = Agenda::factory()->create(['created_by' => $creator->id]);

        $this->actingAs($commenter)->postJson(route('meeting-room.discussions.store', $agenda), [
            'comment' => 'Let\'s sync on this tomorrow.',
        ])->assertOk();

        $this->assertDatabaseHas('agenda_comments', [
            'agenda_id' => $agenda->id,
            'author_id' => $commenter->id,
        ]);

        Notification::assertSentTo($creator, AgendaCommentNotification::class);
        Notification::assertSentTo($bystander, AgendaCommentNotification::class);
        Notification::assertNotSentTo($commenter, AgendaCommentNotification::class);
    }

    public function test_new_comments_are_rejected_once_the_agenda_is_finalized(): void
    {
        $creator = User::factory()->create();
        $agenda = Agenda::factory()->create(['created_by' => $creator->id, 'status' => 'dismissed']);

        $this->actingAs($creator)->postJson(route('meeting-room.discussions.store', $agenda), [
            'comment' => 'Too late.',
        ])->assertForbidden();
    }

    public function test_mentioning_a_user_by_name_notifies_them_as_mentioned(): void
    {
        Notification::fake();

        $creator = User::factory()->create();
        $mentioned = User::factory()->create(['name' => 'Jane Doe']);
        $commenter = User::factory()->create();
        $agenda = Agenda::factory()->create(['created_by' => $creator->id]);

        $this->actingAs($commenter)->postJson(route('meeting-room.discussions.store', $agenda), [
            'comment' => 'Hey @janedoe can you take a look?',
        ])->assertOk();

        Notification::assertSentTo(
            $mentioned,
            AgendaCommentNotification::class,
            fn (AgendaCommentNotification $notification) => str_contains($notification->toArray($mentioned)['message'], 'mentioned you')
        );
    }

    public function test_replies_are_threaded_under_their_parent_comment(): void
    {
        $creator = User::factory()->create();
        $agenda = Agenda::factory()->create(['created_by' => $creator->id]);

        $parent = $agenda->comments()->create(['comment' => 'Top level', 'author_id' => $creator->id]);

        $this->actingAs($creator)->postJson(route('meeting-room.discussions.store', $agenda), [
            'comment' => 'A reply',
            'parent_id' => $parent->id,
        ])->assertOk();

        $this->assertDatabaseHas('agenda_comments', [
            'agenda_id' => $agenda->id,
            'parent_id' => $parent->id,
            'comment' => 'A reply',
        ]);
    }

    public function test_selected_agenda_with_replies_and_creator_controls_renders(): void
    {
        $creator = User::factory()->create();
        $agenda = Agenda::factory()->create(['created_by' => $creator->id]);
        $parent = $agenda->comments()->create(['comment' => 'Top', 'author_id' => $creator->id]);
        $agenda->comments()->create(['comment' => 'A reply', 'author_id' => $creator->id, 'parent_id' => $parent->id]);

        $response = $this->actingAs($creator)->get(route('meeting-room.index', ['agenda' => $agenda->id]));

        $response->assertOk();
        $response->assertSee($agenda->title);
        $response->assertSee('Top');
        $response->assertSee('A reply');
        $response->assertSee('Close');
        $response->assertSee('Dismiss');
    }

    public function test_search_and_status_filters_scope_the_agenda_list(): void
    {
        $user = User::factory()->create();
        Agenda::factory()->create(['title' => 'Marketing budget', 'created_by' => $user->id, 'status' => 'pending']);
        Agenda::factory()->create(['title' => 'Office relocation', 'created_by' => $user->id, 'status' => 'closed']);

        $response = $this->actingAs($user)->get(route('meeting-room.index', ['search' => 'Marketing']));

        $response->assertOk();
        $response->assertViewHas('agendas', fn ($agendas) => $agendas->total() === 1);
    }
}
