<?php

namespace Tests\Feature;

use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CampaignEditorTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Newsletter',
            'channel' => 'email',
            'subject' => 'Hello {{name}}',
            'message_html' => '<p>Hi <strong>{{name}}</strong>,</p><ol><li data-list="bullet">Fast</li><li data-list="bullet">Simple</li></ol>'
                .'<ol><li data-list="ordered">One</li></ol><p><a href="https://acme.test">Visit us</a></p><script>alert(1)</script>',
            'audience' => 'all_leads',
            ...$overrides,
        ];
    }

    public function test_the_email_body_is_written_in_the_editor_and_sent_as_html(): void
    {
        Mail::fake();
        $admin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['contact_person' => '<b>Ram</b> & Co', 'email' => 'ram@acme.test']);

        $this->actingAs($admin)->get(route('campaigns.create'))->assertOk()
            ->assertSee('data-rich-text-toolbar-buttons', false)
            ->assertSee('name="message_html"', false);

        $preview = $this->actingAs($admin)->postJson(route('campaigns.compose-preview'), $this->payload())->assertOk();
        $this->assertStringContainsString('<strong>&lt;b&gt;Ram&lt;/b&gt; &amp; Co</strong>', $preview->json('html'));

        $this->actingAs($admin)->postCampaign($this->payload())->assertSessionHasNoErrors();

        $campaign = Campaign::firstOrFail();
        $this->assertSame('html', $campaign->message_format);
        $this->assertStringNotContainsString('<script', $campaign->message);
        // Quill's bullet <ol> became a real <ul>; the numbered list stayed <ol>.
        $this->assertStringContainsString('<ul><li>Fast</li><li>Simple</li></ul>', $campaign->message);
        $this->assertStringContainsString('<ol><li>One</li></ol>', $campaign->message);

        Mail::assertSent(CampaignMail::class, function (CampaignMail $mail) {
            // HTML part: formatting kept, the merged name escaped so it can't inject markup.
            $mail->assertSeeInHtml('<strong>&lt;b&gt;Ram&lt;/b&gt; &amp; Co</strong>', false)
                ->assertSeeInHtml('<a href="https://acme.test">Visit us</a>', false);

            // Text part: readable plain text.
            return str_contains($mail->renderedBody, 'Hi <b>Ram</b> & Co,')
                && str_contains($mail->renderedBody, '• Fast')
                && str_contains($mail->renderedBody, 'Visit us (https://acme.test)')
                && ! str_contains($mail->renderedBody, '<strong>');
        });

        $this->actingAs($admin)->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('<strong>{{name}}</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_an_empty_editor_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'ram@acme.test']);

        $this->actingAs($admin)->postJson(route('campaigns.compose-preview'), $this->payload(['message_html' => '<p><br></p><p> </p>']))
            ->assertStatus(422)->assertJsonValidationErrors('message');
    }

    public function test_editing_the_body_after_previewing_needs_a_new_preview(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'ram@acme.test']);

        $preview = $this->actingAs($admin)->postJson(route('campaigns.compose-preview'), $this->payload())->assertOk();

        $this->actingAs($admin)->post(route('campaigns.store'), [...$this->payload(['message_html' => '<p>Different</p>']), 'preview_token' => $preview->json('token')])
            ->assertSessionHasErrors('preview_token');
        $this->assertSame(0, Campaign::count());
    }

    public function test_sms_stays_plain_text(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->postCampaign([
            'name' => 'SMS', 'channel' => 'sms', 'message' => 'Hi there', 'message_html' => '<p><strong>ignored</strong></p>',
            'audience' => 'none', 'extra_contacts' => '9800000001',
        ])->assertSessionHasNoErrors();

        $campaign = Campaign::firstOrFail();
        $this->assertSame('text', $campaign->message_format);
        $this->assertSame('Hi there', $campaign->message);
    }

    public function test_plain_text_email_bodies_still_work(): void
    {
        Mail::fake();
        $admin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['contact_person' => 'Ram', 'email' => 'ram@acme.test']);

        $this->actingAs($admin)->postCampaign([...$this->payload(), 'message_html' => null, 'message' => "Hi {{name}},\n<not html>"])
            ->assertSessionHasNoErrors();

        $this->assertSame('text', Campaign::firstOrFail()->message_format);
        Mail::assertSent(CampaignMail::class, fn (CampaignMail $mail) => $mail->assertSeeInHtml('Hi Ram,<br />', false) && $mail->assertSeeInHtml('&lt;not html&gt;', false));
    }

    public function test_an_empty_file_field_from_the_browser_is_ignored(): void
    {
        Mail::fake();
        $admin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'ram@acme.test']);

        // A browser always sends the file input, even with nothing chosen — as an
        // empty upload (→ null) or an empty value. Neither must block the campaign.
        $empty = [...$this->payload(), 'attachments' => ['']];
        $preview = $this->actingAs($admin)->postJson(route('campaigns.compose-preview'), $empty)->assertOk();
        $this->actingAs($admin)->post(route('campaigns.store'), [...$empty, 'preview_token' => $preview->json('token')])->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->call('POST', route('campaigns.compose-preview'), $this->payload(), [], ['attachments' => [null]], ['HTTP_ACCEPT' => 'application/json'])
            ->assertOk();

        $this->assertSame(1, Campaign::count());
        $this->assertSame(0, Campaign::first()->attachments()->count());
    }
}
