<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Agenda;
use App\Models\Lead;
use App\Models\RawData;
use App\Models\Requirement;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\User;
use App\Notifications\MentionedNotification;
use App\Support\MentionParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MentionTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://hooks.slack.test/services/T/B/mentions';

    private User $author;

    private User $jane;

    protected function setUp(): void
    {
        parent::setUp();
        MentionParser::flush();

        $this->author = User::factory()->superAdmin()->create(['name' => 'Karan Khanal']);
        $this->jane = User::factory()->create(['name' => 'Jane Doe']);
    }

    private function userWithoutLeadAccess(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->forceFill(['permissions' => ['leads' => []]])->save();

        return $user;
    }

    // ---- Handles & display ---------------------------------------------------

    public function test_handles_come_from_names_and_same_names_are_told_apart(): void
    {
        $karanTwo = User::factory()->create(['name' => 'Karan Khanal']);

        $this->assertSame('janedoe', MentionParser::handle($this->jane));
        $this->assertSame("karankhanal.{$this->author->id}", MentionParser::handle($this->author));
        $this->assertSame("karankhanal.{$karanTwo->id}", MentionParser::handle($karanTwo));

        $found = MentionParser::extractUsers("ping @karankhanal.{$karanTwo->id} and @JaneDoe. Also @nobody, email@janedoe.com");
        $this->assertEqualsCanonicalizing([$karanTwo->id, $this->jane->id], $found->pluck('id')->all());
    }

    public function test_inactive_people_cannot_be_mentioned(): void
    {
        User::factory()->create(['name' => 'Old Staff', 'status' => UserStatus::Inactive]);

        $this->assertTrue(MentionParser::extractUsers('@oldstaff')->isEmpty());
    }

    public function test_comments_show_mentions_as_name_chips_and_stay_escaped(): void
    {
        $html = (string) MentionParser::highlight("Hi @janedoe, see <script>x</script>\nthanks @nobody", $this->jane);

        $this->assertStringContainsString('<span class="mention mention-me" title="@janedoe">@Jane Doe</span>,', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<br>', $html);
        $this->assertStringContainsString('@nobody', $html);
    }

    // ---- Posting a comment ---------------------------------------------------

    public function test_mentioning_on_a_lead_notifies_only_people_who_can_see_it_and_posts_to_slack(): void
    {
        config(['services.slack.activity_webhook_url' => self::WEBHOOK]);
        Http::fake();
        $blocked = $this->userWithoutLeadAccess('Ram Shrestha');
        $lead = Lead::factory()->create(['company_name' => 'Acme Traders']);

        $this->actingAs($this->author)
            ->post(route('leads.notes.store', $lead), ['comment' => 'Can @janedoe and @ramshrestha call them? cc @karankhanal'])
            ->assertRedirect();

        $notification = $this->jane->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('mention', $notification->data['type']);
        $this->assertSame(route('leads.show', $lead), $notification->data['url']);
        $this->assertStringContainsString('Karan Khanal mentioned you on lead “Acme Traders”', $notification->data['message']);
        $this->assertStringContainsString('Can @Jane Doe and', $notification->data['message'], 'handles become names');

        $this->assertSame(0, $blocked->notifications()->count(), "can't see the lead — not notified");
        $this->assertSame(0, $this->author->notifications()->count(), "mentioning yourself doesn't notify you");

        // Slack goes out after the response (the test request already ran that step).
        $this->app->terminate(); // a second terminate must not post it again

        $mentionPosts = Http::recorded()->filter(fn ($pair) => str_contains($pair[0]['text'], ':speech_balloon:'))->values();
        $this->assertCount(1, $mentionPosts);
        $firstLine = strtok($mentionPosts[0][0]['text'], "\n");
        $this->assertSame(':speech_balloon: *Karan Khanal* mentioned *Jane Doe* on <'.route('leads.show', $lead).'|lead “Acme Traders”>', $firstLine, 'only people who can see the lead are named');
    }

    public function test_mentions_work_on_tasks_requirements_tickets_and_raw_data(): void
    {
        Notification::fake();
        $this->actingAs($this->author);

        $task = Task::factory()->create(['created_by' => $this->jane->id]); // tasks are only visible to people involved
        $requirement = Requirement::factory()->create();
        $ticket = SupportTicket::factory()->create();
        $rawData = RawData::factory()->create();

        $this->post(route('tasks.comments.store', $task), ['comment' => '@janedoe task'])->assertRedirect();
        $this->post(route('requirements.comments.store', $requirement), ['comment' => '@janedoe requirement'])->assertRedirect();
        $this->post(route('support-tickets.comments.store', $ticket), ['comment' => '@janedoe ticket'])->assertRedirect();
        $this->post(route('raw-data.comments.store', $rawData), ['comment' => '@janedoe raw data'])->assertRedirect();

        $urls = [];
        Notification::assertSentTo($this->jane, MentionedNotification::class, function (MentionedNotification $n) use (&$urls) {
            $urls[] = $n->toArray($this->jane)['url'];

            return true;
        });

        $this->assertEqualsCanonicalizing([
            route('tasks.show', $task), route('requirements.show', $requirement),
            route('support-tickets.show', $ticket), route('raw-data.show', $rawData),
        ], $urls);
    }

    public function test_editing_a_ticket_comment_only_notifies_newly_mentioned_people(): void
    {
        $bikash = User::factory()->create(['name' => 'Bikash KC']);
        $ticket = SupportTicket::factory()->create();

        $this->actingAs($this->author)->post(route('support-tickets.comments.store', $ticket), ['comment' => 'Hi @janedoe']);
        $comment = $ticket->comments()->first();

        $this->patch(route('support-tickets.comments.update', [$ticket, $comment]), ['comment' => 'Hi @janedoe and @bikashkc']);

        $this->assertSame(1, $this->jane->notifications()->count(), 'Jane was already notified');
        $this->assertSame(1, $bikash->notifications()->count());
    }

    public function test_agenda_mentions_post_to_slack_without_a_second_bell_notification(): void
    {
        config(['services.slack.activity_webhook_url' => self::WEBHOOK]);
        Http::fake();
        $agenda = Agenda::factory()->create(['title' => 'Q3 pricing']);

        $this->actingAs($this->author)
            ->postJson(route('meeting-room.discussions.store', $agenda), ['comment' => 'thoughts @janedoe?'])
            ->assertOk()
            ->assertJsonPath('comment.comment_html', 'thoughts <span class="mention" title="@janedoe">@Jane Doe</span>?');

        $this->assertSame(0, $this->jane->notifications()->where('type', MentionedNotification::class)->count());
        Http::assertSent(fn (Request $request) => str_contains($request['text'], 'mentioned *Jane Doe* on') && str_contains($request['text'], 'agenda “Q3 pricing”'));
    }

    // ---- Suggestions for the composer ----------------------------------------

    public function test_suggestions_list_only_teammates_who_can_see_the_record(): void
    {
        $this->userWithoutLeadAccess('Ram Shrestha');
        User::factory()->create(['name' => 'Old Staff', 'status' => UserStatus::Inactive]);
        $lead = Lead::factory()->create();

        $names = $this->actingAs($this->author)
            ->getJson(route('mentions.users', ['lead', $lead->id]))
            ->assertOk()
            ->json('*.name');

        $this->assertContains('Jane Doe', $names);
        $this->assertNotContains('Karan Khanal', $names, 'not the author');
        $this->assertNotContains('Ram Shrestha', $names, "not someone who can't see leads");
        $this->assertNotContains('Old Staff', $names, 'not an inactive account');
        $jane = collect($this->getJson(route('mentions.users', ['lead', $lead->id]))->json())->firstWhere('name', 'Jane Doe');
        $this->assertSame('janedoe', $jane['handle']);
    }

    public function test_suggestions_are_refused_for_a_record_you_cannot_see(): void
    {
        $lead = Lead::factory()->create();

        $this->actingAs($this->userWithoutLeadAccess('Ram Shrestha'))
            ->getJson(route('mentions.users', ['lead', $lead->id]))
            ->assertForbidden();

        $this->actingAs($this->author)->getJson('/mentions/nonsense/1')->assertNotFound();
    }
}
