<?php

namespace Tests\Feature;

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\CampaignAwaitingApprovalNotification;
use App\Notifications\CampaignReviewedNotification;
use App\Support\CampaignSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function submit(User $creator, array $overrides = []): Campaign
    {
        $this->actingAs($creator)->postCampaign([
            'name' => 'Dashain offer',
            'channel' => 'email',
            'subject' => 'Offer for {{company_name}}',
            'message' => "Hi {{name}},\nHappy Dashain!",
            'audience' => 'all_leads',
            ...$overrides,
        ])->assertSessionHasNoErrors();

        return Campaign::latest('id')->firstOrFail();
    }

    public function test_a_non_super_admin_campaign_waits_for_approval_and_super_admins_are_notified(): void
    {
        Queue::fake();
        Mail::fake();
        Notification::fake();
        $admin = $this->user(UserRole::SuperAdmin);
        $inactiveAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'status' => UserStatus::Inactive]);
        $creator = $this->user(UserRole::BusinessDevelopment);
        Lead::factory()->count(2)->sequence(fn ($s) => ['email' => "lead{$s->index}@example.com"])->create();

        $this->actingAs($creator)->get(route('campaigns.create'))->assertSee('Submit for Approval');

        $campaign = $this->submit($creator);

        $this->assertSame(CampaignStatus::AwaitingApproval, $campaign->status);
        $this->assertSame(2, $campaign->recipient_count);
        $this->assertSame(2, $campaign->recipients()->where('status', CampaignRecipientStatus::Pending)->count());
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
        Notification::assertSentTo($admin, CampaignAwaitingApprovalNotification::class);
        Notification::assertNotSentTo($inactiveAdmin, CampaignAwaitingApprovalNotification::class);
        Notification::assertNotSentTo($creator, CampaignAwaitingApprovalNotification::class);

        // The scheduler never starts a campaign that hasn't been approved.
        $this->artisan('campaigns:dispatch-due');
        Queue::assertNothingPushed();

        $this->assertSame(CampaignStatus::AwaitingApproval, $this->submit($this->user(UserRole::Manager))->status);
    }

    public function test_the_super_admin_reviews_a_full_preview_and_summary(): void
    {
        $settings = app(CampaignSettings::class);
        $settings->set('campaign_signature', '<p>Ram Sharma, Acme</p>');
        $admin = $this->user(UserRole::SuperAdmin);
        $creator = $this->user(UserRole::BusinessDevelopment);
        Lead::factory()->create(['company_name' => 'Acme', 'contact_person' => 'Sita', 'email' => 'sita@acme.test']);
        Contact::factory()->create(['email' => 'contact@example.com']);
        $campaign = $this->submit($creator, ['all_contacts' => '1', 'extra_contacts' => "extra@example.com\nnot-an-email"]);

        $this->actingAs($admin)->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('Awaiting approval')
            ->assertSee('Subject:')
            ->assertSee('Offer for Acme')
            ->assertSee('1 lead(s) · 1 contact(s) · 1 extra')
            ->assertSee('1 duplicate/invalid/unsubscribed')
            ->assertSee("As soon as it's approved", false)
            ->assertSee(route('campaigns.email-preview', [$campaign, 'as' => $campaign->recipients()->orderBy('id')->value('id')]), false)
            ->assertSee('Approve &amp; send to 3 recipient(s)', false)
            ->assertSee('Reject…');

        // The preview is the real email, personalized, with signature and footer.
        $sita = $campaign->recipients()->where('address', 'sita@acme.test')->value('id');
        $preview = $this->actingAs($admin)->get(route('campaigns.email-preview', [$campaign, 'as' => $sita]));
        $preview->assertOk()->assertHeader('Content-Security-Policy')
            ->assertSee('Hi Sita,', false)->assertSee('Ram Sharma, Acme', false)->assertSee('Unsubscribe');

        // The creator sees the same preview, but can't approve it.
        $this->actingAs($creator)->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('A Super Admin needs to approve this')
            ->assertDontSee('Approve &amp; send', false);
        $this->actingAs($creator)->get(route('campaigns.email-preview', $campaign))->assertOk();
        // Anyone allowed to view campaigns can see it; not someone without that permission.
        $this->actingAs($this->user(UserRole::BusinessDevelopment))->get(route('campaigns.email-preview', $campaign))->assertOk();
        $noView = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $noView->forceFill(['permissions' => ['campaigns' => []]])->save();
        $this->actingAs($noView)->get(route('campaigns.email-preview', $campaign))->assertForbidden();
    }

    public function test_approving_sends_it_and_tells_the_creator(): void
    {
        Mail::fake();
        Notification::fake();
        $admin = $this->user(UserRole::SuperAdmin);
        $creator = $this->user(UserRole::BusinessDevelopment);
        Lead::factory()->create(['email' => 'ram@acme.test']);
        $campaign = $this->submit($creator);

        $this->actingAs($this->user(UserRole::Manager))->post(route('campaigns.approve', $campaign))->assertForbidden();
        $this->actingAs($creator)->post(route('campaigns.approve', $campaign))->assertForbidden();

        $this->actingAs($admin)->post(route('campaigns.approve', $campaign))->assertSessionHas('success', 'Campaign approved — sending has started.');

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Completed, $campaign->status);
        $this->assertSame($admin->id, $campaign->reviewed_by);
        $this->assertNotNull($campaign->reviewed_at);
        Mail::assertSent(CampaignMail::class, fn ($mail) => $mail->hasTo('ram@acme.test'));
        Notification::assertSentTo($creator, CampaignReviewedNotification::class, fn ($n) => str_contains($n->toArray($creator)['message'], 'approved'));

        // Only once.
        $this->actingAs($admin)->post(route('campaigns.approve', $campaign))->assertForbidden();
        $this->actingAs($admin)->get(route('campaigns.show', $campaign))->assertSee('Approved by '.$admin->name);
    }

    public function test_an_approved_scheduled_campaign_waits_for_its_time(): void
    {
        Queue::fake();
        $admin = $this->user(UserRole::SuperAdmin);
        Lead::factory()->create(['email' => 'ram@acme.test']);
        $campaign = $this->submit($this->user(UserRole::BusinessDevelopment), ['scheduled_at' => now()->addHours(2)->format('Y-m-d\TH:i')]);

        $this->actingAs($admin)->post(route('campaigns.approve', $campaign))->assertSessionHas('success', fn ($m) => str_contains($m, 'it sends on'));
        $this->assertSame(CampaignStatus::Pending, $campaign->fresh()->status);
        Queue::assertNothingPushed();

        $this->travel(3)->hours();
        $this->artisan('campaigns:dispatch-due');
        $this->assertSame(CampaignStatus::Sending, $campaign->fresh()->status);
    }

    public function test_a_schedule_that_passed_while_waiting_sends_on_approval(): void
    {
        Queue::fake();
        Lead::factory()->create(['email' => 'ram@acme.test']);
        $campaign = $this->submit($this->user(UserRole::BusinessDevelopment), ['scheduled_at' => now()->addHour()->format('Y-m-d\TH:i')]);

        $this->travel(2)->hours();
        $this->artisan('campaigns:dispatch-due');
        $this->assertSame(CampaignStatus::AwaitingApproval, $campaign->fresh()->status);

        $this->actingAs($this->user(UserRole::SuperAdmin))->get(route('campaigns.show', $campaign))->assertSee("sends as soon as it's approved", false);
        $this->actingAs($this->user(UserRole::SuperAdmin))->post(route('campaigns.approve', $campaign));
        $this->assertSame(CampaignStatus::Sending, $campaign->fresh()->status);
    }

    public function test_rejecting_needs_a_reason_sends_nothing_and_tells_the_creator(): void
    {
        Mail::fake();
        Notification::fake();
        $admin = $this->user(UserRole::SuperAdmin);
        $creator = $this->user(UserRole::BusinessDevelopment);
        Lead::factory()->create(['email' => 'ram@acme.test']);
        $campaign = $this->submit($creator);

        $this->actingAs($admin)->post(route('campaigns.reject', $campaign), ['review_note' => ' '])->assertSessionHasErrors('review_note');
        $this->assertSame(CampaignStatus::AwaitingApproval, $campaign->fresh()->status);

        $this->actingAs($admin)->post(route('campaigns.reject', $campaign), ['review_note' => 'Subject has a typo.'])->assertSessionHas('success');

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Rejected, $campaign->status);
        $this->assertSame('Subject has a typo.', $campaign->review_note);
        $this->assertSame(1, $campaign->recipients()->where('status', CampaignRecipientStatus::Cancelled)->count());
        Mail::assertNothingSent();
        Notification::assertSentTo($creator, CampaignReviewedNotification::class, fn ($n) => str_contains($n->toArray($creator)['message'], 'rejected your campaign "Dashain offer": Subject has a typo.'));

        $this->actingAs($creator)->get(route('campaigns.show', $campaign))->assertSee('Rejected by '.$admin->name)->assertSee('Subject has a typo.');
        $this->actingAs($admin)->post(route('campaigns.approve', $campaign))->assertForbidden();
        $this->actingAs($creator)->post(route('campaigns.cancel', $campaign))->assertForbidden();
    }

    public function test_the_creator_can_withdraw_a_campaign_while_it_waits(): void
    {
        $creator = $this->user(UserRole::BusinessDevelopment);
        Lead::factory()->create(['email' => 'ram@acme.test']);
        $campaign = $this->submit($creator);

        $this->actingAs($creator)->post(route('campaigns.cancel', $campaign))->assertSessionHas('success');
        $this->assertSame(CampaignStatus::Cancelled, $campaign->fresh()->status);
    }

    public function test_super_admins_see_what_is_waiting_in_the_list_and_sidebar(): void
    {
        $admin = $this->user(UserRole::SuperAdmin);
        Lead::factory()->create(['email' => 'ram@acme.test']);
        $waiting = $this->submit($this->user(UserRole::BusinessDevelopment), ['name' => 'Needs review']);
        Campaign::factory()->create(['name' => 'Old one', 'created_at' => now()->addMinute()]);

        $this->actingAs($admin)->get(route('campaigns.index'))->assertOk()
            ->assertSee('1 campaign(s) awaiting approval.')
            ->assertSeeInOrder(['Needs review', 'Old one'])
            ->assertSee('Review');
        $this->actingAs($admin)->get(route('campaigns.index', ['status' => 'awaiting_approval']))->assertOk()
            ->assertSee('Needs review')->assertDontSee('Old one');
        $this->actingAs($admin)->get(route('dashboard'))->assertSee('1 to approve');

        $this->actingAs($admin)->post(route('campaigns.approve', $waiting));
        $this->actingAs($admin)->get(route('dashboard'))->assertDontSee('to approve');
    }

    public function test_sms_campaigns_preview_the_personalized_text(): void
    {
        $creator = $this->user(UserRole::BusinessDevelopment);
        Lead::factory()->create(['contact_person' => 'Sita', 'phone' => '9800000001']);
        $campaign = $this->submit($creator, ['channel' => 'sms', 'message' => 'Namaste {{name}}']);

        $this->actingAs($this->user(UserRole::SuperAdmin))->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('Namaste Sita')->assertSee('To 9800000001');
        $this->actingAs($creator)->get(route('campaigns.email-preview', $campaign))->assertNotFound();
    }
}
