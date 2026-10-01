<?php

namespace App\Services;

use App\Enums\MailEncryption;
use App\Mail\CampaignMail;
use App\Models\EmailAccount;
use App\Support\CampaignSettings;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

/**
 * Sends campaign email from the sender chosen in Campaign Setup: one of
 * the saved Email Accounts, a dedicated SMTP login entered on the setup
 * page (each built on the fly — the global mail config is never touched),
 * or the app's MAIL_* settings.
 */
class CampaignMailer
{
    public function __construct(protected CampaignSettings $settings)
    {
    }

    public function send(string $to, CampaignMail $mail): void
    {
        $account = $this->settings->emailAccount();
        [$address, $name] = $this->from($account);

        $mail->from($address, $name);

        if ($replyTo = $this->settings->get('campaign_reply_to')) {
            $mail->replyTo($replyTo);
        }

        $this->mailer($account)->to($to)->send($mail);
    }

    /**
     * A campaign email with this setup's signature and footer. The token
     * (the recipient's) drives the unsubscribe link and the open-tracking
     * image; a test email passes none.
     */
    public function compose(string $subject, string $body, ?string $token, bool $includeSignature = true, bool $trackOpens = true): CampaignMail
    {
        return new CampaignMail(
            $subject,
            $body,
            trackingUrl: $token && $trackOpens ? route('campaigns.track-open', $token) : null,
            unsubscribeUrl: $token ? route('campaigns.unsubscribe', $token) : null,
            signatureHtml: $includeSignature ? $this->settings->signature() : null,
            footer: $this->settings->get('campaign_footer'),
        );
    }

    /**
     * The From address and name. An Email Account always sends as its own
     * address — a different From would fail SPF/DMARC alignment and land
     * in spam — but its display name can be overridden.
     *
     * @return array{0: string, 1: ?string}
     */
    public function from(?EmailAccount $account = null): array
    {
        $account ??= $this->settings->emailAccount();
        $name = $this->settings->get('campaign_from_name');

        if ($account) {
            return [$account->email_address, $name ?? ($account->display_name ?: null)];
        }

        return [
            $this->settings->get('campaign_from_address', $this->settings->emailMode() === 'smtp' ? $this->settings->get('campaign_smtp_username') : null)
                ?? (string) config('mail.from.address'),
            $name ?? config('mail.from.name'),
        ];
    }

    /**
     * Where campaign email currently comes from, for the setup page.
     */
    public function senderDescription(): string
    {
        $account = $this->settings->emailAccount();
        [$address, $name] = $this->from($account);
        $from = $name ? "{$name} <{$address}>" : $address;

        return match (true) {
            $account !== null => "{$from} via Email Account ({$account->smtp_host})",
            $this->settings->emailMode() === 'smtp' => "{$from} via dedicated SMTP ({$this->settings->get('campaign_smtp_host', 'host not set')})",
            default => "{$from} via system mail settings (.env, ".config('mail.mailers.smtp.host').')',
        };
    }

    private function mailer(?EmailAccount $account): Mailer
    {
        if ($account) {
            return $this->smtp($account->smtp_host, $account->smtp_port, $account->smtp_encryption, $account->username, $account->password);
        }

        if ($this->settings->emailMode() === 'smtp') {
            return $this->smtp(
                (string) $this->settings->get('campaign_smtp_host'),
                (int) $this->settings->get('campaign_smtp_port', '587'),
                MailEncryption::tryFrom((string) $this->settings->get('campaign_smtp_encryption')) ?? MailEncryption::Tls,
                $this->settings->get('campaign_smtp_username'),
                $this->settings->smtpPassword(),
            );
        }

        return Mail::mailer();
    }

    private function smtp(string $host, int $port, ?MailEncryption $encryption, ?string $username, ?string $password): Mailer
    {
        return Mail::build([
            'transport' => 'smtp',
            'scheme' => $encryption === MailEncryption::Ssl ? 'smtps' : 'smtp',
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'timeout' => 30,
            // Plain (unencrypted) accounts must not be upgraded to STARTTLS.
            'auto_tls' => $encryption !== MailEncryption::None,
        ]);
    }
}
