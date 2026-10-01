<?php

namespace App\Support;

/**
 * Looks up the DNS records that decide whether campaign email reaches the
 * inbox: SPF (which servers may send for the domain), DKIM (signature
 * key), DMARC (policy tying the two to the From address) and MX. Gmail
 * and Yahoo reject or spam-folder bulk mail without them.
 */
class SenderDomainCheck
{
    /** Free mailbox providers — fine for one-to-one mail, not for bulk sending. */
    private const FREE_PROVIDERS = ['gmail.com', 'googlemail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'live.com', 'icloud.com', 'aol.com', 'proton.me', 'protonmail.com'];

    /** Selectors used by common hosts: cPanel, Google Workspace, Microsoft 365, Zoho, Mailchimp/Mandrill, Brevo, generic. */
    private const DKIM_SELECTORS = ['default', 'google', 'selector1', 'selector2', 'zoho', 'k1', 'mandrill', 'brevo', 'mail', 'dkim', 's1', 's2'];

    /**
     * @return array{domain: string, checks: array<int, array{key: string, label: string, status: string, detail: string}>}
     */
    public function check(string $fromAddress): array
    {
        $domain = mb_strtolower(trim((string) substr(strrchr($fromAddress, '@') ?: '', 1)));
        $checks = [];

        if ($domain === '') {
            return ['domain' => '', 'checks' => [['key' => 'from', 'label' => 'From address', 'status' => 'missing', 'detail' => 'No From address is set.']]];
        }

        if (in_array($domain, self::FREE_PROVIDERS, true)) {
            $checks[] = ['key' => 'free', 'label' => 'Sender domain', 'status' => 'warn',
                'detail' => "{$domain} is a free mailbox provider. Bulk email from it is usually spam-foldered or blocked — send from your own domain."];
        }

        $spf = array_values(array_filter($this->txt($domain), fn ($r) => str_starts_with(mb_strtolower($r), 'v=spf1')));
        $checks[] = match (count($spf)) {
            0 => ['key' => 'spf', 'label' => 'SPF', 'status' => 'missing', 'detail' => "No SPF record on {$domain}. Add the TXT record your mail host gives you (cPanel: Email Deliverability)."],
            1 => ['key' => 'spf', 'label' => 'SPF', 'status' => 'ok', 'detail' => $spf[0]],
            default => ['key' => 'spf', 'label' => 'SPF', 'status' => 'warn', 'detail' => 'More than one SPF record — receivers treat that as no SPF. Merge them into one.'],
        };

        $dkimFound = null;
        foreach (self::DKIM_SELECTORS as $selector) {
            foreach ($this->txt("{$selector}._domainkey.{$domain}") as $record) {
                if (str_contains(mb_strtolower($record), 'p=')) {
                    $dkimFound = $selector;
                    break 2;
                }
            }
        }
        $checks[] = $dkimFound
            ? ['key' => 'dkim', 'label' => 'DKIM', 'status' => 'ok', 'detail' => "Key found (selector \"{$dkimFound}\")."]
            : ['key' => 'dkim', 'label' => 'DKIM', 'status' => 'warn', 'detail' => 'No DKIM key under the common selectors. Turn on DKIM at your mail host (cPanel: Email Deliverability) — if it uses its own selector it may already be set.'];

        $dmarc = array_values(array_filter($this->txt("_dmarc.{$domain}"), fn ($r) => str_starts_with(mb_strtolower($r), 'v=dmarc1')));
        $checks[] = $dmarc
            ? ['key' => 'dmarc', 'label' => 'DMARC', 'status' => 'ok', 'detail' => $dmarc[0]]
            : ['key' => 'dmarc', 'label' => 'DMARC', 'status' => 'missing', 'detail' => "No DMARC record. Add a TXT record on _dmarc.{$domain}, e.g. v=DMARC1; p=none; rua=mailto:postmaster@{$domain}"];

        $checks[] = $this->hasMx($domain)
            ? ['key' => 'mx', 'label' => 'MX', 'status' => 'ok', 'detail' => 'The domain can receive mail (replies and bounces).']
            : ['key' => 'mx', 'label' => 'MX', 'status' => 'warn', 'detail' => 'No MX record — replies and bounces to this address will fail.'];

        return ['domain' => $domain, 'checks' => $checks];
    }

    /**
     * @return array<int, string>
     */
    protected function txt(string $host): array
    {
        $records = @dns_get_record($host, DNS_TXT);

        return array_map(fn (array $r) => (string) ($r['txt'] ?? implode('', $r['entries'] ?? [])), $records ?: []);
    }

    protected function hasMx(string $domain): bool
    {
        return (bool) @dns_get_record($domain, DNS_MX);
    }
}
