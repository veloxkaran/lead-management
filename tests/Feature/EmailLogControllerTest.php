<?php

namespace Tests\Feature;

use App\Enums\EmailLogStatus;
use App\Models\EmailLog;
use App\Models\Lead;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_super_admin_cannot_access_the_email_log(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('email-logs.index'))->assertForbidden();
    }

    public function test_super_admin_can_view_the_email_log(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $lead = Lead::factory()->create();
        $requirement = Requirement::factory()->create(['lead_id' => $lead->id]);

        $log = EmailLog::create([
            'to_email' => 'client@example.test',
            'subject' => 'A test notification',
            'body' => 'Body text',
            'template_key' => 'requirement_created',
            'status' => EmailLogStatus::Sent,
            'sent_at' => now(),
        ]);
        $log->related()->associate($requirement)->save();

        $response = $this->actingAs($superAdmin)->get(route('email-logs.index'));

        $response->assertOk();
        $response->assertSee('client@example.test');
        $response->assertSee('A test notification');

        $showResponse = $this->actingAs($superAdmin)->get(route('email-logs.show', $log));
        $showResponse->assertOk();
        $showResponse->assertSee('Body text');
    }

    public function test_index_can_be_filtered_by_status(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        EmailLog::create([
            'to_email' => 'sent@example.test',
            'subject' => 'Sent one',
            'body' => 'Body',
            'status' => EmailLogStatus::Sent,
            'sent_at' => now(),
        ]);

        EmailLog::create([
            'to_email' => 'failed@example.test',
            'subject' => 'Failed one',
            'body' => 'Body',
            'status' => EmailLogStatus::Failed,
            'error' => 'Connection refused',
        ]);

        $response = $this->actingAs($superAdmin)->get(route('email-logs.index', ['status' => 'failed']));

        $response->assertOk();
        $response->assertSee('failed@example.test');
        $response->assertDontSee('sent@example.test');
    }

    public function test_index_can_be_filtered_by_custom_date_range(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $old = EmailLog::create([
            'to_email' => 'old@example.test',
            'subject' => 'Old one',
            'body' => 'Body',
            'status' => EmailLogStatus::Sent,
        ]);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        EmailLog::create([
            'to_email' => 'recent@example.test',
            'subject' => 'Recent one',
            'body' => 'Body',
            'status' => EmailLogStatus::Pending,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('email-logs.index', [
            'period' => 'custom',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('recent@example.test');
        $response->assertDontSee('old@example.test');
    }

    public function test_pending_log_is_marked_failed_when_the_job_gives_up(): void
    {
        $log = EmailLog::create([
            'to_email' => 'client@example.test',
            'subject' => 'Subject',
            'body' => 'Body',
            'status' => EmailLogStatus::Pending,
        ]);

        (new \App\Jobs\SendClientNotificationEmail($log))->failed(new \RuntimeException('Worker timed out'));

        $log->refresh();
        $this->assertSame(EmailLogStatus::Failed, $log->status);
        $this->assertSame('Worker timed out', $log->error);
    }
}
