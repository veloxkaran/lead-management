<?php

namespace App\Services;

use App\Enums\MailEncryption;
use App\Mail\CampaignMail;
use App\Support\CampaignSettings;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

/**
 * Sends campaign email through the campaign login in .env (CAMPAIGN_MAIL_*,
 * built on the fly — the global mail config is never touched). Without
 * CAMPAIGN_MAIL_HOST it falls back to the app's own MAIL_* settings, and
 * Campaign Setup warns about it.
 */
class CampaignMailer
{
    public function __construct(protected CampaignSettings $settings)
    {
    }

    public function send(string $to, CampaignMail $mail): void
    {
        $this->mailer()->to($to)->send($this->prepare($mail));
    }

    /**
     * Sets From and Reply-To from the campaign login.
     */
    public function prepare(CampaignMail $mail): CampaignMail
    {
        [$address, $name] = $this->from();

        $mail->from($address, $name);

        if ($replyTo = $this->settings->envMail()['reply_to'] ?? null) {
            $mail->replyTo($replyTo);
        }

        return $mail;
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
     * The From address and name: CAMPAIGN_MAIL_FROM_* (the address falls
     * back to the login username), else MAIL_FROM_*.
     *
     * @return array{0: string, 1: ?string}
     */
    public function from(): array
    {
        $env = $this->settings->envMail();

        return [
            ($env['from_address'] ?? null) ?: (($env['username'] ?? null) ?: (string) config('mail.from.address')),
            ($env['from_name'] ?? null) ?: config('mail.from.name'),
        ];
    }

    /**
     * Where campaign email currently comes from, for the setup page and composer.
     */
    public function senderDescription(): string
    {
        [$address, $name] = $this->from();
        $from = $name ? "{$name} <{$address}>" : $address;
        $env = $this->settings->envMail();

        return $env
            ? "{$from} via {$env['username']} at {$env['host']}"
            : "{$from} via the app's MAIL_* settings (CAMPAIGN_MAIL_* isn't set)";
    }

    private function mailer(): Mailer
    {
        $env = $this->settings->envMail();

        if (! $env) {
            return Mail::mailer();
        }

        $encryption = MailEncryption::tryFrom(strtolower((string) $env['encryption'])) ?? MailEncryption::Ssl;

        return Mail::build([
            'transport' => 'smtp',
            'scheme' => $encryption === MailEncryption::Ssl ? 'smtps' : 'smtp',
            'host' => (string) $env['host'],
            'port' => (int) $env['port'],
            'username' => $env['username'] ?: null,
            'password' => $env['password'] ?: null,
            'timeout' => 30,
            // Plain (unencrypted) servers must not be upgraded to STARTTLS.
            'auto_tls' => $encryption !== MailEncryption::None,
        ]);
    }
}
