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
}
