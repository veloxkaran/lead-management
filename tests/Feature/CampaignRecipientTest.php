<?php

namespace Tests\Feature;

use App\Enums\CampaignAudience;
use App\Enums\CampaignChannel;
use App\Models\Industry;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use App\Support\CampaignRecipientBuilder;
use App\Support\ContactNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignRecipientTest extends TestCase
{
    use RefreshDatabase;

    private function build(CampaignChannel $channel, CampaignAudience $audience, array $filter = [], array $leadIds = [], ?string $extra = null)
    {
        return app(CampaignRecipientBuilder::class)->build($channel, $audience, $filter, $leadIds, $extra);
    }

    public function test_phone_numbers_are_normalized_for_duplicate_detection(): void
    {
        $this->assertSame('9800000000', ContactNormalizer::phone('+977-980-0000000'));
        $this->assertSame('9800000000', ContactNormalizer::phone('00977 9800000000'));
        $this->assertSame('9800000000', ContactNormalizer::phone('(980) 000-0000'));
        $this->assertSame('9771234567', ContactNormalizer::phone('9771234567'), 'a 10-digit local number starting 977 is left alone');
        $this->assertNull(ContactNormalizer::phone('12345'));
        $this->assertNull(ContactNormalizer::phone('call me'));
        $this->assertSame('ram@example.com', ContactNormalizer::email('  Ram@Example.COM '));
        $this->assertNull(ContactNormalizer::email('not-an-email'));
    }

    public function test_leads_sharing_an_email_get_it_once_and_the_duplicate_is_reported(): void
    {
        Lead::factory()->create(['company_name' => 'First Co', 'email' => 'shared@example.com']);
        Lead::factory()->create(['company_name' => 'Second Co', 'email' => 'SHARED@example.com ']);
        Lead::factory()->create(['company_name' => 'Other Co', 'email' => 'other@example.com']);

        $list = $this->build(CampaignChannel::Email, CampaignAudience::AllLeads);

        $this->assertSame(2, $list->count());
        $this->assertSame(1, $list->duplicateCount());
        $this->assertStringContainsString('First Co', $list->skipped[0]['reason']);
        $this->assertSame('duplicate', $list->skipped[0]['type']);
    }

    public function test_leads_without_a_valid_contact_are_listed_as_left_out(): void
    {
        Lead::factory()->create(['company_name' => 'No Phone Co', 'phone' => null]);
        Lead::factory()->create(['company_name' => 'Bad Phone Co', 'phone' => 'n/a']);
        Lead::factory()->create(['company_name' => 'Good Co', 'phone' => '9800000001']);

        $list = $this->build(CampaignChannel::Sms, CampaignAudience::AllLeads);

        $this->assertSame(['9800000001'], array_column($list->recipients, 'address'));
        $this->assertSame(['invalid', 'invalid'], array_column($list->skipped, 'type'));
        $this->assertSame(['No Phone Co', 'Bad Phone Co'], array_column($list->skipped, 'value'));
    }

    public function test_extra_numbers_are_deduplicated_against_each_other_and_against_leads(): void
    {
        $lead = Lead::factory()->create(['company_name' => 'Lead Co', 'contact_person' => 'Lead Person', 'phone' => '9800000001']);
        $unselected = Lead::factory()->create(['company_name' => 'Elsewhere Co', 'contact_person' => 'Other Person', 'phone' => '9800000009']);

        $list = $this->build(CampaignChannel::Sms, CampaignAudience::None, [], [$lead->id], implode("\n", [
            '+977 9800000001',          // same as the picked lead
            '9811111111',
            '981-111-1111',             // same as the line above
            'Sita Sharma, 9822222222',  // named contact
            '9800000009',               // belongs to a lead that wasn't picked
            'ram@example.com',          // email in an SMS campaign
            'hello',                    // not a number at all
        ]));

        $this->assertSame(4, $list->count());
        $this->assertSame(1, $list->fromLeads);
        $this->assertSame(3, $list->manual);
        $this->assertSame(1, $list->linkedToLeads);

        $byAddress = collect($list->recipients)->keyBy('address');
        $this->assertSame('Sita Sharma', $byAddress['9822222222']['name']);
        $this->assertSame($unselected->id, $byAddress['9800000009']['lead_id']);
        $this->assertSame('Other Person', $byAddress['9800000009']['name']);

        $reasons = collect($list->skipped)->pluck('reason', 'value');
        $this->assertStringContainsString('already included as lead "Lead Co"', $reasons['+977 9800000001']);
        $this->assertStringContainsString('Duplicate', $reasons['981-111-1111']);
        $this->assertStringContainsString('Looks like an email address', $reasons['ram@example.com']);
        $this->assertStringContainsString('Not a valid phone number', $reasons['hello']);
        $this->assertSame(2, $list->duplicateCount());
    }

    public function test_a_lead_reached_through_audience_and_picked_list_is_not_a_duplicate(): void
    {
        $lead = Lead::factory()->create(['email' => 'one@example.com']);

        $list = $this->build(CampaignChannel::Email, CampaignAudience::AllLeads, [], [$lead->id]);

        $this->assertSame(1, $list->count());
        $this->assertSame([], $list->skipped);
    }

    public function test_comma_separated_contacts_on_one_line_are_split(): void
    {
        $list = $this->build(CampaignChannel::Email, CampaignAudience::None, [], [], 'a@example.com, b@example.com; c@example.com');

        $this->assertSame(['a@example.com', 'b@example.com', 'c@example.com'], array_column($list->recipients, 'address'));
    }

    public function test_audiences_select_the_right_leads_and_skip_archived_ones(): void
    {
        $won = LeadStatus::factory()->create(['is_closed_won' => true]);
        $open = LeadStatus::factory()->create(['is_closed_won' => false]);
        Industry::factory()->create(['name' => 'Finance']);

        Lead::factory()->create(['email' => 'customer@example.com', 'lead_status_id' => $won->id, 'industry' => 'Retail']);
        Lead::factory()->create(['email' => 'prospect@example.com', 'lead_status_id' => $open->id, 'industry' => 'Finance']);
        Lead::factory()->create(['email' => 'archived@example.com', 'lead_status_id' => $open->id, 'industry' => 'Finance', 'archived_at' => now()]);

        $addresses = fn ($list) => array_column($list->recipients, 'address');

        $this->assertSame(['customer@example.com'], $addresses($this->build(CampaignChannel::Email, CampaignAudience::Customers)));
        $this->assertSame(['prospect@example.com'], $addresses($this->build(CampaignChannel::Email, CampaignAudience::LeadStatuses, [$open->id])));
        $this->assertSame(['prospect@example.com'], $addresses($this->build(CampaignChannel::Email, CampaignAudience::Industries, ['Finance'])));
        $this->assertSame(['customer@example.com', 'prospect@example.com'], $addresses($this->build(CampaignChannel::Email, CampaignAudience::AllLeads)));
        $this->assertSame([], $addresses($this->build(CampaignChannel::Email, CampaignAudience::None)));
    }

    public function test_preview_endpoint_reports_totals_and_skipped_entries(): void
    {
        $user = User::factory()->create();
        Lead::factory()->create(['company_name' => 'Lead Co', 'email' => 'lead@example.com']);

        $this->actingAs($user)->postJson(route('campaigns.preview'), [
            'channel' => 'email',
            'audience' => 'all_leads',
            'extra_contacts' => "lead@example.com\nnew@example.com\nbroken@",
        ])
            ->assertOk()
            ->assertJson([
                'total' => 2,
                'from_leads' => 1,
                'manual' => 1,
                'duplicates' => 1,
                'skipped_count' => 2,
            ])
            ->assertJsonPath('skipped.0.value', 'lead@example.com')
            ->assertJsonPath('skipped.1.type', 'invalid');
    }

    public function test_preview_validates_the_audience_filter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('campaigns.preview'), ['channel' => 'sms', 'audience' => 'industries'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('industries');
    }
}
