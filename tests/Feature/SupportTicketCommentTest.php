<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_user_can_post_a_comment_on_a_ticket(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($user)->post(route('support-tickets.comments.store', $ticket), [
            'comment' => 'Looking into this now.',
        ])->assertRedirect();

        $this->assertDatabaseHas('support_ticket_comments', [
            'support_ticket_id' => $ticket->id,
            'author_id' => $user->id,
            'comment' => 'Looking into this now.',
        ]);
    }

    public function test_author_can_edit_their_own_comment_within_four_hours(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();
        $comment = $ticket->comments()->create(['comment' => 'Original text', 'author_id' => $user->id]);

        $this->actingAs($user)->patch(route('support-tickets.comments.update', [$ticket, $comment]), [
            'comment' => 'Edited text',
        ])->assertRedirect();

        $this->assertSame('Edited text', $comment->fresh()->comment);
    }

    public function test_comment_becomes_uneditable_after_four_hours(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();
        $comment = $ticket->comments()->create(['comment' => 'Original text', 'author_id' => $user->id]);
        $comment->forceFill(['created_at' => now()->subHours(5)])->save();

        $this->actingAs($user)->patch(route('support-tickets.comments.update', [$ticket, $comment]), [
            'comment' => 'Too late edit',
        ])->assertForbidden();

        $this->assertSame('Original text', $comment->fresh()->comment);
    }

    public function test_details_page_shows_ticket_details_and_comments(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create();
        $ticket->comments()->create(['comment' => 'A comment here', 'author_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('support-tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee($ticket->subject);
        $response->assertSee('A comment here');
        $response->assertSee('Edit');
    }

    public function test_a_user_cannot_edit_someone_elses_comment(): void
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $ticket = SupportTicket::factory()->create();
        $comment = $ticket->comments()->create(['comment' => 'Original text', 'author_id' => $author->id]);

        $this->actingAs($otherUser)->patch(route('support-tickets.comments.update', [$ticket, $comment]), [
            'comment' => 'Hijacked edit',
        ])->assertForbidden();

        $this->assertSame('Original text', $comment->fresh()->comment);
    }
}
