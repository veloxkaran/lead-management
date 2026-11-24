<?php

namespace Tests\Feature;

use App\Models\Requirement;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Support ticket details and requirement descriptions share one rich-text
 * pipeline, so they look the same when viewed as when typed.
 */
class RichTextDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_plain_text_keeps_its_paragraphs_and_line_breaks(): void
    {
        $html = RichText::render("Line one\nLine two\n\nNew paragraph & <stuff");

        $this->assertStringContainsString('Line one<br', $html);
        $this->assertStringContainsString('Line two</p>', $html);
        $this->assertStringContainsString('<p>New paragraph &amp; &lt;stuff</p>', $html);
    }

    public function test_html_is_sanitized(): void
    {
        $html = RichText::render('<p><strong>Bold</strong></p><script>alert(1)</script>');

        $this->assertStringContainsString('<strong>Bold</strong>', $html);
        $this->assertStringNotContainsString('script', $html);
    }

    public function test_quill_bullet_lists_stay_bulleted_and_mixed_lists_split(): void
    {
        $html = RichText::sanitize(
            '<ol><li data-list="bullet">Apple</li><li data-list="bullet">Pear</li>'
            .'<li data-list="ordered">First</li></ol><p>After</p>'
        );

        $this->assertSame('<ul><li>Apple</li><li>Pear</li></ul><ol><li>First</li></ol><p>After</p>', $this->squish($html));
    }

    public function test_ticket_details_are_sanitized_on_create_and_render_like_a_requirement(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('support-tickets.store'), [
            'subject' => 'Rich ticket',
            'type' => 'support',
            'priority' => 'medium',
            'details' => '<p>Steps:</p><ol><li data-list="bullet">Open <em>page</em></li></ol><script>x()</script>',
        ])->assertRedirect();

        $ticket = SupportTicket::where('subject', 'Rich ticket')->firstOrFail();
        $this->assertSame('<p>Steps:</p><ul><li>Open <em>page</em></li></ul>', $this->squish($ticket->details));

        $this->actingAs($user)->get(route('support-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('<div class="rich-content">', false)
            ->assertSee('<ul><li>Open <em>page</em></li></ul>', false);
    }

    public function test_legacy_plain_text_ticket_details_show_their_line_breaks(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create(['details' => "First line\nSecond line"]);

        $this->actingAs($user)->get(route('support-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('First line<br', false);

        $this->assertSame("First line\nSecond line", $ticket->detailsText());
    }

    public function test_plain_text_from_the_lead_quick_form_is_stored_with_its_line_breaks(): void
    {
        $user = User::factory()->create();
        $requirement = Requirement::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)->post(route('leads.support-tickets.store', $requirement->lead), [
            'subject' => 'Quick ticket',
            'type' => 'support',
            'priority' => 'medium',
            'details' => "One\nTwo",
        ])->assertRedirect();

        $this->assertSame('<p>One<br>Two</p>', SupportTicket::where('subject', 'Quick ticket')->value('details'));
    }

    public function test_ticket_edit_page_uses_the_editor_until_details_lock_then_shows_them_read_only(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create(['details' => "Line A\nLine B"]);

        $this->actingAs($user)->get(route('support-tickets.edit', $ticket))
            ->assertOk()
            ->assertSee('data-rich-text-editor', false)
            // Legacy plain text is handed to the editor as paragraphs so its line breaks survive.
            ->assertSee(e('<p>Line A<br>Line B</p>'), false);

        $ticket->forceFill(['created_at' => now()->subHours(13)])->save();

        $this->actingAs($user)->get(route('support-tickets.edit', $ticket))
            ->assertOk()
            ->assertDontSee('name="details"', false)
            ->assertSee('Line A<br', false);
    }

    /** Purifier puts blank lines between block tags; irrelevant to rendering. */
    private function squish(string $html): string
    {
        return preg_replace('/>\s+</', '><', trim($html));
    }

    public function test_locked_details_resubmitted_unchanged_after_editor_reformatting_are_not_rejected(): void
    {
        $user = User::factory()->create();
        $ticket = SupportTicket::factory()->create(['subject' => 'Locked', 'details' => 'Legacy text']);
        $ticket->forceFill(['created_at' => now()->subHours(13)])->save();

        $this->actingAs($user)->put(route('support-tickets.update', $ticket), [
            'subject' => 'Locked',
            'details' => '<p>Legacy text</p>',
            'priority' => $ticket->priority->value,
            'status' => 'in_progress',
        ])->assertSessionHasNoErrors();

        $this->assertSame('in_progress', $ticket->fresh()->status->value);
    }
}
