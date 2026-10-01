<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role = UserRole::BusinessDevelopment, ?array $permissions = null): User
    {
        $user = User::factory()->create(['role' => $role]);

        if ($permissions !== null) {
            $user->forceFill(['permissions' => $permissions])->save();
        }

        return $user;
    }

    public function test_a_contact_can_be_added_with_any_single_field(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('contacts.store'), ['phone' => '9800000000'])
            ->assertRedirect(route('contacts.index'))->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->actingAs($user)->post(route('contacts.store'), ['company_name' => '  Acme Pvt. Ltd. ', 'name' => '', 'email' => ' Ram@Acme.TEST '])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('contacts', ['phone' => '9800000000', 'name' => null, 'email' => null, 'company_name' => null, 'created_by' => $user->id]);
        $this->assertDatabaseHas('contacts', ['company_name' => 'Acme Pvt. Ltd.', 'email' => 'ram@acme.test', 'name' => null]);
    }

    public function test_an_empty_contact_is_rejected_and_the_form_reopens(): void
    {
        $user = $this->user();

        $this->actingAs($user)->from(route('contacts.index'))->post(route('contacts.store'), ['company_name' => ' ', 'email' => ''])
            ->assertRedirect(route('contacts.index'))
            ->assertSessionHasErrors('contact');

        $this->assertSame(0, Contact::count());
        $this->actingAs($user)->get(route('contacts.index'))->assertSee('Fill in at least one field')->assertSee('reopen: true', false);
    }

    public function test_emails_and_phones_are_validated_and_emails_must_be_unique(): void
    {
        $user = $this->user();
        Contact::factory()->create(['name' => 'Ram Sharma', 'email' => 'ram@acme.test']);

        $this->actingAs($user)->post(route('contacts.store'), ['email' => 'not-an-email', 'phone' => 'call me'])
            ->assertSessionHasErrors(['email', 'phone']);

        $this->actingAs($user)->post(route('contacts.store'), ['email' => 'RAM@acme.test'])
            ->assertSessionHasErrors(['email' => 'This email is already saved for "Ram Sharma".']);

        // A deleted contact's email can be used again.
        Contact::where('email', 'ram@acme.test')->first()->delete();
        $this->actingAs($user)->post(route('contacts.store'), ['email' => 'ram@acme.test'])->assertSessionHasNoErrors();
    }

    public function test_save_and_add_another_reopens_an_empty_form(): void
    {
        $this->actingAs($this->user())->post(route('contacts.store'), ['name' => 'Sita', 'add_another' => '1'])
            ->assertSessionHas('contactFormOpen', true);
    }

    public function test_editing_keeps_its_own_email_and_clears_blanked_fields(): void
    {
        $user = $this->user();
        $contact = Contact::factory()->create(['email' => 'sita@example.com', 'phone' => '9811111111', 'created_by' => $user->id]);

        $this->actingAs($user)->put(route('contacts.update', $contact), ['name' => 'Sita Thapa', 'email' => 'sita@example.com', 'phone' => '', 'company_name' => ''])
            ->assertSessionHasNoErrors();

        $contact->refresh();
        $this->assertSame('Sita Thapa', $contact->name);
        $this->assertNull($contact->phone);
        $this->assertNull($contact->company_name);

        $this->actingAs($user)->put(route('contacts.update', $contact), ['name' => '', 'email' => ''])->assertSessionHasErrors('contact');
    }

    public function test_only_the_creator_a_manager_or_a_super_admin_can_change_a_contact(): void
    {
        $owner = $this->user();
        $colleague = $this->user();
        $contact = Contact::factory()->create(['created_by' => $owner->id]);

        $this->actingAs($colleague)->get(route('contacts.index'))->assertOk()->assertSee($contact->email);
        $this->actingAs($colleague)->put(route('contacts.update', $contact), ['name' => 'X'])->assertForbidden();
        $this->actingAs($colleague)->delete(route('contacts.destroy', $contact))->assertForbidden();

        $this->actingAs($this->user(UserRole::Manager))->put(route('contacts.update', $contact), ['name' => 'By manager'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->delete(route('contacts.destroy', $contact))->assertSessionHas('success');
        $this->assertSoftDeleted($contact);
    }

    public function test_roles_outside_outreach_and_restricted_members_cannot_use_contacts(): void
    {
        foreach ([UserRole::CustomerSuccess, UserRole::Finance] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->get(route('contacts.index'))->assertForbidden();
            $this->actingAs($user)->get(route('dashboard'))->assertDontSee(route('contacts.index'));
        }

        $viewer = $this->user(UserRole::BusinessDevelopment, ['contacts' => ['view']]);
        $this->actingAs($viewer)->get(route('contacts.index'))->assertOk()->assertDontSee('openCreate()', false)->assertDontSee(route('contacts.import'));
        $this->actingAs($viewer)->post(route('contacts.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->get(route('contacts.import'))->assertForbidden();

        $this->actingAs($this->user())->get(route('dashboard'))->assertSee(route('contacts.index'));
        $this->actingAs($this->user(UserRole::SuperAdmin))->get(route('users.permissions.edit', $viewer))->assertSee('Contacts');
    }

    public function test_search_filters_and_sorting(): void
    {
        $user = $this->user();
        Contact::factory()->create(['company_name' => 'Zeta Traders', 'name' => 'Hari', 'email' => 'hari@zeta.test', 'phone' => null]);
        Contact::factory()->create(['company_name' => 'Alpha Co', 'name' => 'Gita', 'email' => null, 'phone' => '980-555-1234', 'created_by' => $user->id]);

        $this->actingAs($user)->get(route('contacts.index', ['search' => 'zeta']))->assertSee('Zeta Traders')->assertDontSee('Alpha Co');
        $this->actingAs($user)->get(route('contacts.index', ['search' => '9805551234']))->assertSee('Alpha Co')->assertDontSee('Zeta Traders');
        $this->actingAs($user)->get(route('contacts.index', ['filter' => 'email']))->assertSee('Zeta Traders')->assertDontSee('Alpha Co');
        $this->actingAs($user)->get(route('contacts.index', ['filter' => 'phone']))->assertSee('Alpha Co')->assertDontSee('Zeta Traders');
        $this->actingAs($user)->get(route('contacts.index', ['filter' => 'mine']))->assertSee('Alpha Co')->assertDontSee('Zeta Traders');
        $this->actingAs($user)->get(route('contacts.index', ['sort' => 'company']))->assertSeeInOrder(['Alpha Co', 'Zeta Traders']);
        $this->actingAs($user)->get(route('contacts.index', ['search' => 'nobody']))->assertSee('No contacts match');
    }

    public function test_bulk_delete_only_removes_contacts_the_user_may_delete(): void
    {
        $user = $this->user();
        $mine = Contact::factory()->count(2)->create(['created_by' => $user->id]);
        $theirs = Contact::factory()->create(['created_by' => $this->user()->id]);

        $this->actingAs($user)->post(route('contacts.bulk-destroy'), ['ids' => [...$mine->modelKeys(), $theirs->id]])
            ->assertSessionHas('success', '2 contact(s) deleted. 1 added by someone else were left — only they, a Manager or a Super Admin can delete those.');

        $this->assertSame([$theirs->id], Contact::pluck('id')->all());
    }

    public function test_pasted_rows_are_imported_with_skipped_rows_explained(): void
    {
        $user = $this->user();
        Contact::factory()->create(['email' => 'existing@example.com']);

        $rows = implode("\n", [
            "Name\tPhone\tEmail\tCompany",
            "Ram Sharma\t9800000000\tram@acme.test\tAcme",
            "Sita\t\t\t",
            "\t\texisting@example.com\t",
            "Hari\t\tnot-an-email\t",
            "Gita\t\tRAM@acme.test\t",
            "\t\t\t",
        ]);

        $this->actingAs($user)->post(route('contacts.import.store'), ['rows' => $rows])
            ->assertRedirect(route('contacts.import'))
            ->assertSessionHas('success', '2 contact(s) imported.');

        $this->assertDatabaseHas('contacts', ['name' => 'Ram Sharma', 'phone' => '9800000000', 'email' => 'ram@acme.test', 'company_name' => 'Acme', 'created_by' => $user->id]);
        $this->assertDatabaseHas('contacts', ['name' => 'Sita', 'email' => null]);

        $result = session('importResult');
        $this->assertSame(2, $result['imported']);
        $this->assertSame([
            ['row' => 4, 'reason' => 'Email is already saved as a contact'],
            ['row' => 5, 'reason' => 'Email "not-an-email" isn\'t valid'],
            ['row' => 6, 'reason' => 'Same email as row 2'],
        ], array_map(fn ($s) => ['row' => $s['row'], 'reason' => $s['reason']], $result['skipped']));

        $this->actingAs($user)->get(route('contacts.import'))->assertSee('Same email as row 2');
    }

    public function test_rows_without_a_header_are_read_by_their_contents(): void
    {
        $this->actingAs($this->user())->post(route('contacts.import.store'), ['rows' => "Ram Sharma, ram@acme.test, +977 9800000000\nSita, sita@acme.test,"])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('contacts', ['name' => 'Ram Sharma', 'email' => 'ram@acme.test', 'phone' => '+977 9800000000', 'company_name' => null]);
        $this->assertDatabaseHas('contacts', ['name' => 'Sita', 'email' => 'sita@acme.test']);
    }

    public function test_a_spreadsheet_file_can_be_imported(): void
    {
        $csv = UploadedFile::fake()->createWithContent('contacts.csv', "Company Name,Name,Email,Phone\nAcme,Ram,ram@acme.test,9800000000\n,,,\nBeta,,,9811111111\n");

        $this->actingAs($this->user())->post(route('contacts.import.store'), ['file' => $csv])
            ->assertSessionHas('success', '2 contact(s) imported.');

        $this->assertDatabaseHas('contacts', ['company_name' => 'Beta', 'phone' => '9811111111']);
        $this->actingAs($this->user())->post(route('contacts.import.store'), [])->assertSessionHasErrors(['rows', 'file']);
        $this->actingAs($this->user())->get(route('contacts.import.template'))->assertOk()->assertDownload('contacts-template.xlsx');
    }

    public function test_contacts_can_be_exported(): void
    {
        Contact::factory()->count(3)->create();

        $this->actingAs($this->user())->get(route('contacts.export'))->assertOk()->assertDownload();
    }

    public function test_campaigns_can_go_to_all_or_picked_contacts(): void
    {
        Mail::fake();
        $user = $this->user(UserRole::SuperAdmin);
        $ram = Contact::factory()->create(['name' => 'Ram', 'company_name' => 'Acme', 'email' => 'ram@acme.test']);
        $sita = Contact::factory()->create(['name' => 'Sita', 'email' => 'sita@acme.test']);
        Contact::factory()->create(['name' => 'Phone only', 'email' => null]);
        Lead::factory()->create(['email' => 'sita@acme.test', 'company_name' => 'Sita Lead']);

        // Picked from the Contacts list: the form opens pre-filled.
        $this->actingAs($user)->get(route('campaigns.create', ['channel' => 'email', 'audience' => 'none', 'contact_ids' => [$ram->id]]))
            ->assertOk()->assertSee('1 contact(s) picked from the Contacts list');

        $this->actingAs($user)->postJson(route('campaigns.preview'), ['channel' => 'email', 'audience' => 'none', 'all_contacts' => '1'])
            ->assertOk()->assertJson(['total' => 2, 'from_contacts' => 2]);

        // The lead and the contact share an email — sent once, as the lead.
        $this->actingAs($user)->postCampaign([
            'name' => 'To contacts', 'channel' => 'email', 'subject' => 'Hi {{name}}', 'message' => 'Hello {{company_name}}',
            'audience' => 'all_leads', 'all_contacts' => '1', 'contact_ids' => [$ram->id],
        ])->assertSessionHasNoErrors();

        $campaign = Campaign::firstOrFail();
        $this->assertTrue($campaign->all_contacts);
        $this->assertSame([$ram->id], $campaign->contact_ids);
        $this->assertSame(2, $campaign->recipient_count);
        $this->assertSame($ram->id, CampaignRecipient::where('address', 'ram@acme.test')->value('contact_id'));
        $this->assertNotNull(CampaignRecipient::where('address', 'sita@acme.test')->value('lead_id'));
        $this->assertStringContainsString('Same email address as lead "Sita Lead"', collect($campaign->skipped)->pluck('reason')->implode(' '));
        Mail::assertSent(\App\Mail\CampaignMail::class, fn ($mail) => $mail->hasTo('ram@acme.test') && $mail->renderedSubject === 'Hi Ram' && $mail->renderedBody === 'Hello Acme');

        $this->actingAs($user)->get(route('campaigns.show', $campaign))->assertOk()->assertSee('+ all contacts')->assertSee('Contact');
    }
}
