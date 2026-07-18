<?php

namespace App\Services;

use App\Enums\ActivityModule;
use App\Models\ActivityLogEntry;
use App\Models\EmailAccount;
use App\Models\User;
use App\Repositories\EmailAccountRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EmailAccountService
{
    public function __construct(
        protected EmailAccountRepository $accounts,
        protected EmailAccountConnectionTester $connectionTester,
    ) {
    }

    public function list(array $filters, User $viewer, int $perPage = 20): LengthAwarePaginator
    {
        return $this->accounts->filter($filters, $viewer->id, $perPage);
    }

    public function create(array $attributes, User $actor): EmailAccount
    {
        $attributes['user_id'] = $actor->id;

        // The user's very first account is automatically their default —
        // otherwise respect what was submitted (unsetting any prior default).
        $hasExistingAccounts = EmailAccount::where('user_id', $actor->id)->exists();
        $attributes['is_default'] = ! $hasExistingAccounts || ! empty($attributes['is_default']);

        return DB::transaction(function () use ($attributes, $actor) {
            if ($attributes['is_default']) {
                EmailAccount::where('user_id', $actor->id)->update(['is_default' => false]);
            }

            /** @var EmailAccount $account */
            $account = $this->accounts->create($attributes);

            return $account;
        });
    }

    public function update(EmailAccount $account, array $attributes, User $actor, ?string $ip, ?string $userAgent): EmailAccount
    {
        // getDirty() (after fill(), before save()) vs getRawOriginal() keeps
        // the diff correct regardless of what happens after save(), and keeps
        // old/new symmetric (both raw storage values) — same pattern as
        // TaskService::update().
        $originalRaw = collect(array_keys($attributes))
            ->mapWithKeys(fn ($key) => [$key => $account->getRawOriginal($key)])
            ->all();

        $account->fill($attributes);
        $changed = $account->getDirty();
        unset($changed['updated_at']);

        $account->save();

        if (! empty($changed)) {
            $old = array_intersect_key($originalRaw, $changed);
            $new = $changed;

            // The audit log's old_values/new_values columns are plain,
            // unencrypted JSON — never let the password (even ciphertext)
            // land in there.
            if (array_key_exists('password', $old)) {
                $old['password'] = '[redacted]';
            }
            if (array_key_exists('password', $new)) {
                $new['password'] = '[redacted]';
            }

            $this->logChange($account, $actor, $ip, $userAgent, $old, $new, "updated email account \"{$account->email_address}\"");
        }

        return $account;
    }

    public function delete(EmailAccount $account, User $actor, ?string $ip, ?string $userAgent): void
    {
        $this->logActivity($account, $actor, $ip, $userAgent, "deleted email account \"{$account->email_address}\"");

        $this->accounts->delete($account);
    }

    public function testConnection(EmailAccount $account, User $actor, ?string $ip, ?string $userAgent): EmailAccount
    {
        $result = $this->connectionTester->test($account);

        $account->update([
            'connection_status' => $result['status'],
            'connection_error' => $result['error'],
            'last_tested_at' => now(),
        ]);

        $this->logActivity(
            $account, $actor, $ip, $userAgent,
            "tested connection for email account \"{$account->email_address}\" ({$result['status']->label()})"
        );

        return $account;
    }

    public function setDefault(EmailAccount $account, User $actor): EmailAccount
    {
        DB::transaction(function () use ($account, $actor) {
            EmailAccount::where('user_id', $actor->id)->update(['is_default' => false]);
            $account->update(['is_default' => true]);
        });

        return $account->fresh();
    }

    public function toggleActive(EmailAccount $account, User $actor, ?string $ip, ?string $userAgent): EmailAccount
    {
        $account->update(['is_active' => ! $account->is_active]);

        $this->logActivity(
            $account, $actor, $ip, $userAgent,
            ($account->is_active ? 'enabled' : 'disabled')." email account \"{$account->email_address}\""
        );

        return $account;
    }

    private function logChange(EmailAccount $account, User $actor, ?string $ip, ?string $userAgent, array $oldValues, array $newValues, string $description): void
    {
        ActivityLogEntry::create([
            'company_id' => $account->company_id ?? $actor->company_id,
            'user_id' => $actor->id,
            'module' => ActivityModule::Email,
            'description' => $description,
            'subject_type' => $account->getMorphClass(),
            'subject_id' => $account->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    private function logActivity(EmailAccount $account, User $actor, ?string $ip, ?string $userAgent, string $description): void
    {
        ActivityLogEntry::create([
            'company_id' => $account->company_id ?? $actor->company_id,
            'user_id' => $actor->id,
            'module' => ActivityModule::Email,
            'description' => $description,
            'subject_type' => $account->getMorphClass(),
            'subject_id' => $account->getKey(),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
