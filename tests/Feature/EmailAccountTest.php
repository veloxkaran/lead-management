<?php

namespace Tests\Feature;

use App\Enums\ActivityModule;
use App\Enums\ConnectionStatus;
use App\Enums\EmailProvider;
use App\Enums\MailEncryption;
use App\Models\ActivityLogEntry;
use App\Models\EmailAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailAccountTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'provider' => EmailProvider::CustomSmtp->value,
            'email_address' => 'user@example.com',
            'display_name' => 'Test User',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_encryption' => MailEncryption::Tls->value,
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => MailEncryption::Ssl->value,
            'username' => 'user@example.com',
            'password' => 'secret-password',
        ], $overrides);
    }

    public function test_user_can_create_and_view_their_own_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('email-accounts.store'), $this->validPayload())
            ->assertRedirect(route('email-accounts.index'));

        $account = EmailAccount::firstWhere('email_address', 'user@example.com');
        $this->assertNotNull($account);
        $this->assertSame($user->id, $account->user_id);
        $this->assertTrue($account->is_default, 'first account should auto-default');

        // Password is round-trippable via the encrypted cast, and stored as
        // ciphertext in the raw DB column, not plaintext.
        $this->assertSame('secret-password', $account->password);
        $rawPassword = \DB::table('email_accounts')->where('id', $account->id)->value('password');
        $this->assertNotSame('secret-password', $rawPassword);

        $this->actingAs($user)->get(route('email-accounts.edit', $account))->assertOk();
    }

    public function test_a_user_cannot_view_edit_or_delete_someone_elses_account(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger)->get(route('email-accounts.edit', $account))->assertForbidden();
        $this->actingAs($stranger)->put(route('email-accounts.update', $account), $this->validPayload())->assertForbidden();
        $this->actingAs($stranger)->delete(route('email-accounts.destroy', $account))->assertForbidden();

        // The stranger's own list never includes the owner's account.
        $this->actingAs($stranger)->get(route('email-accounts.index'))
            ->assertOk()
            ->assertViewHas('accounts', fn ($accounts) => $accounts->total() === 0);
    }

    public function test_setting_a_new_default_unsets_the_previous_one(): void
    {
        $user = User::factory()->create();
        $first = EmailAccount::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $second = EmailAccount::factory()->create(['user_id' => $user->id, 'is_default' => false]);

        $this->actingAs($user)->post(route('email-accounts.set-default', $second))->assertRedirect();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_toggle_active_flips_status(): void
    {
        $user = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $user->id, 'is_active' => true]);

        $this->actingAs($user)->patch(route('email-accounts.toggle-active', $account))->assertRedirect();
        $this->assertFalse($account->fresh()->is_active);

        $this->actingAs($user)->patch(route('email-accounts.toggle-active', $account))->assertRedirect();
        $this->assertTrue($account->fresh()->is_active);
    }

    public function test_an_address_can_be_reused_after_the_original_is_soft_deleted(): void
    {
        $user = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $user->id, 'email_address' => 'reuse@example.com']);

        $this->actingAs($user)->delete(route('email-accounts.destroy', $account))->assertRedirect();
        $this->assertSoftDeleted('email_accounts', ['id' => $account->id]);

        $this->actingAs($user)->post(route('email-accounts.store'), $this->validPayload(['email_address' => 'reuse@example.com']))
            ->assertRedirect(route('email-accounts.index'));

        $this->assertDatabaseHas('email_accounts', ['email_address' => 'reuse@example.com', 'deleted_at' => null]);
    }

    public function test_a_duplicate_active_address_for_the_same_user_is_rejected(): void
    {
        $user = User::factory()->create();
        EmailAccount::factory()->create(['user_id' => $user->id, 'email_address' => 'dup@example.com']);

        $this->actingAs($user)->post(route('email-accounts.store'), $this->validPayload(['email_address' => 'dup@example.com']))
            ->assertSessionHasErrors('email_address');
    }

    public function test_connection_test_against_an_unreachable_host_fails_with_an_error_message(): void
    {
        $user = User::factory()->create();
        $account = EmailAccount::factory()->create([
            'user_id' => $user->id,
            'smtp_host' => '127.0.0.1',
            'smtp_port' => 1, // nothing listens here — fails fast, deterministic in CI
            'imap_host' => null,
        ]);

        $this->actingAs($user)->post(route('email-accounts.test-connection', $account))->assertRedirect();

        $account->refresh();
        $this->assertSame(ConnectionStatus::Failed, $account->connection_status);
        $this->assertNotNull($account->connection_error);
        $this->assertNotNull($account->last_tested_at);
    }

    public function test_updating_the_password_is_redacted_in_the_audit_log(): void
    {
        $user = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->put(route('email-accounts.update', $account), $this->validPayload([
            'email_address' => $account->email_address,
            'password' => 'brand-new-secret',
        ]))->assertRedirect();

        $entry = ActivityLogEntry::where('module', ActivityModule::Email)
            ->where('subject_type', $account->getMorphClass())
            ->where('subject_id', $account->id)
            ->where('description', 'like', 'updated%')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('[redacted]', $entry->old_values['password'] ?? null);
        $this->assertSame('[redacted]', $entry->new_values['password'] ?? null);
        $this->assertStringNotContainsString('brand-new-secret', json_encode($entry->new_values));
    }

    public function test_leaving_password_blank_on_update_keeps_the_existing_credential(): void
    {
        $user = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $user->id, 'password' => 'original-secret']);

        $this->actingAs($user)->put(route('email-accounts.update', $account), $this->validPayload([
            'email_address' => $account->email_address,
            'password' => '',
        ]))->assertRedirect();

        $this->assertSame('original-secret', $account->fresh()->password);
    }
}
