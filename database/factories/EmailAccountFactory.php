<?php

namespace Database\Factories;

use App\Enums\ConnectionStatus;
use App\Enums\EmailProvider;
use App\Enums\MailEncryption;
use App\Models\EmailAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailAccount>
 */
class EmailAccountFactory extends Factory
{
    protected $model = EmailAccount::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => EmailProvider::CustomSmtp->value,
            'email_address' => fake()->unique()->safeEmail(),
            'display_name' => fake()->name(),
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_encryption' => MailEncryption::Tls->value,
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => MailEncryption::Ssl->value,
            'username' => fake()->userName(),
            'password' => fake()->password(),
            'connection_status' => ConnectionStatus::NotTested->value,
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
