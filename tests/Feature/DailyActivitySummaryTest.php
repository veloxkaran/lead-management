<?php

namespace Tests\Feature;

use App\Enums\RequirementStatus;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\SupportTicket;
use App\Models\SupportTicketStatusLog;
use App\Models\User;
use App\Services\DailyActivitySummary;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DailyActivitySummaryTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://hooks.slack.test/services/T/B/daily';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-19 16:20:00');
    }

    private function seedDay(): Lead
    {
        $asha = User::factory()->create(['name' => 'Asha Rai']);
        $bikash = User::factory()->create(['name' => 'Bikash KC']);

        Carbon::setTestNow('2026-11-19 09:15:00');
        $lead = Lead::factory()->create(['company_name' => 'Acme Traders', 'created_by' => $asha->id]); // logged by the activity feed

        Carbon::setTestNow('2026-11-19 11:40:00');
        LeadNote::factory()->create(['lead_id' => $lead->id, 'author_id' => $bikash->id, 'comment' => '<p>Asked for a <b>demo</b> next week</p>']);

        Carbon::setTestNow('2026-11-19 13:05:00');
        $ticket = SupportTicket::factory()->create(['lead_id' => $lead->id, 'subject' => 'Payroll export broken', 'raised_by' => $asha->id]);

        Carbon::setTestNow('2026-11-19 15:30:00');
        SupportTicketStatusLog::create(['support_ticket_id' => $ticket->id, 'from_status' => RequirementStatus::InProgress, 'to_status' => RequirementStatus::Completed, 'changed_by' => $bikash->id]);

        // Yesterday — must not appear.
        Carbon::setTestNow('2026-11-18 10:00:00');
        LeadNote::factory()->create(['lead_id' => $lead->id, 'author_id' => $asha->id, 'comment' => 'Old note from yesterday']);

        Carbon::setTestNow('2026-11-19 16:20:00');

        return $lead;
    }

    public function test_summary_lists_todays_events_in_order_with_company_who_and_when(): void
    {
        $this->seedDay();

        $text = app(DailyActivitySummary::class)->slackText(now());

        $this->assertStringContainsString('CRM summary for Thu, Nov 19, 2026', $text);
        $this->assertStringContainsString('_SN | Company | What happened | Who | When_', $text);
        $this->assertStringContainsString('• 1 | *Acme Traders* | created a new lead: Acme Traders | Asha Rai | 9:15 AM', $text);
        $this->assertStringContainsString('• 2 | *Acme Traders* | added a note: Asked for a demo next week | Bikash KC | 11:40 AM', $text);
        $this->assertStringContainsString('• 3 | *Acme Traders* | raised a support ticket: Payroll export broken | Asha Rai | 1:05 PM', $text);
        $this->assertStringContainsString('• 4 | *Acme Traders* | resolved a support ticket: Payroll export broken | Bikash KC | 3:30 PM', $text);
        $this->assertStringNotContainsString('yesterday', $text);
        $this->assertStringContainsString('4 updates · 1 company · 2 people', $text);
    }

    public function test_company_names_link_to_the_lead_when_the_app_url_is_public(): void
    {
        $lead = $this->seedDay();
        config(['app.url' => 'https://crm.hajirhr.com']);

        $text = app(DailyActivitySummary::class)->slackText(now());

        $this->assertStringContainsString('*<'.route('leads.show', $lead).'|Acme Traders>*', $text);
    }

    public function test_a_quiet_day_still_posts_a_short_message(): void
    {
        $text = app(DailyActivitySummary::class)->slackText(now());

        $this->assertStringContainsString('No activity was recorded in the CRM today.', $text);
    }

    public function test_command_posts_to_the_activity_webhook(): void
    {
        $this->seedDay();
        config(['services.slack.activity_webhook_url' => self::WEBHOOK]);
        Http::fake([self::WEBHOOK => Http::response('ok')]);

        $this->artisan('crm:daily-activity-summary')->assertSuccessful()->expectsOutputToContain('posted to Slack');

        Http::assertSent(fn ($request) => $request->url() === self::WEBHOOK && str_contains($request['text'], 'resolved a support ticket'));
    }

    public function test_dry_run_prints_without_posting(): void
    {
        $this->seedDay();
        config(['services.slack.activity_webhook_url' => self::WEBHOOK]);
        Http::fake();

        $this->artisan('crm:daily-activity-summary', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Acme Traders');

        Http::assertNothingSent();
    }

    public function test_it_is_scheduled_daily_at_4_20_pm(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => $event->description === 'crm-daily-activity-summary');

        $this->assertNotNull($event);
        $this->assertSame('20 16 * * *', $event->expression);
    }
}
