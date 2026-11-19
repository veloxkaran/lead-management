<?php

namespace App\Support;

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\NoRFCWarningsValidation;
use Illuminate\Support\Facades\Cache;

/**
 * "Can this address receive email at all?" — checked right before each
 * campaign / announcement email is sent, so mail to dead addresses is never
 * attempted. Every bounce counts against the sending domain's reputation,
 * and enough of them push all its mail into spam folders.
 *
 *   1. format    — a real address per RFC 5322, no "warnings-only" oddities
 *   2. typos     — gmial.com, gmail.con… (says what it probably meant)
 *   3. throwaway — disposable inbox services
 *   4. domain    — has a mail server: an MX record, or an A/AAAA record
 *                  (RFC 5321's implicit MX); a "null MX" (RFC 7505) means
 *                  the domain explicitly accepts no mail
 *
 * Deliberately NOT done: connecting to the recipient's server to ask
 * whether the mailbox exists ("SMTP probing"). Big providers answer "yes"
 * to everything, shared hosts block the port, and repeated probing gets
 * the sender blacklisted — it would hurt delivery, not help it.
 *
 * Never blocks a real address over a server-side DNS outage: when the
 * domain lookup fails, gmail.com is looked up too; if that fails as well,
 * the server's DNS is down and the result is "unknown" (send anyway).
 */
class RecipientEmailCheck
{
    public const VALID = 'valid';

    public const INVALID = 'invalid';

    public const UNKNOWN = 'unknown';

    /** Domains that are certainly fine — skips a DNS lookup for most recipients. */
    private const KNOWN_PROVIDERS = [
        'gmail.com', 'googlemail.com', 'yahoo.com', 'ymail.com', 'hotmail.com', 'outlook.com', 'live.com',
        'msn.com', 'icloud.com', 'me.com', 'aol.com', 'proton.me', 'protonmail.com', 'zoho.com', 'yandex.com', 'gmx.com',
    ];

    /** Common misspellings of big providers => what was meant. */
    private const TYPOS = [
        'gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gamil.com' => 'gmail.com', 'gnail.com' => 'gmail.com',
        'gmaill.com' => 'gmail.com', 'gmail.co' => 'gmail.com', 'gmail.con' => 'gmail.com', 'gmail.cm' => 'gmail.com',
        'gmail.om' => 'gmail.com', 'gmail.comm' => 'gmail.com', 'gmal.com' => 'gmail.com', 'gmali.com' => 'gmail.com',
        'yaho.com' => 'yahoo.com', 'yahooo.com' => 'yahoo.com', 'yahoo.con' => 'yahoo.com', 'yahoo.co' => 'yahoo.com',
        'hotmial.com' => 'hotmail.com', 'hotmai.com' => 'hotmail.com', 'hotmail.con' => 'hotmail.com', 'hotmal.com' => 'hotmail.com',
        'outlok.com' => 'outlook.com', 'outlook.con' => 'outlook.com', 'outllook.com' => 'outlook.com',
        'iclod.com' => 'icloud.com', 'icloud.con' => 'icloud.com',
    ];

    /** Throwaway inbox services — nobody reads these. */
    private const DISPOSABLE = [
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'sharklasers.com', '10minutemail.com',
        'tempmail.com', 'temp-mail.org', 'tempmailo.com', 'yopmail.com', 'trashmail.com', 'getnada.com',
        'dispostable.com', 'maildrop.cc', 'throwawaymail.com', 'fakeinbox.com', 'mintemail.com', 'mohmal.com',
    ];

    private const CACHE_VALID_SECONDS = 7 * 86400;

    private const CACHE_INVALID_SECONDS = 6 * 3600;

    /**
     * @return array{status: string, reason: ?string}
     */
    public function check(?string $address): array
    {
        $address = mb_strtolower(trim((string) $address));

        if ($address === '') {
            return $this->invalid('No email address.');
        }

        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false
            || ! (new EmailValidator)->isValid($address, new NoRFCWarningsValidation)) {
            return $this->invalid("\"{$address}\" isn't a valid email address.");
        }

        $domain = substr(strrchr($address, '@'), 1);

        if (isset(self::TYPOS[$domain])) {
            $suggestion = substr($address, 0, -strlen($domain)).self::TYPOS[$domain];

            return $this->invalid("\"{$domain}\" looks like a typo — did you mean {$suggestion}?");
        }

        if (in_array($domain, self::DISPOSABLE, true)) {
            return $this->invalid("{$domain} is a throwaway (disposable) inbox.");
        }

        if (in_array($domain, self::KNOWN_PROVIDERS, true) || ! config('mail.verify_recipient_domains')) {
            return ['status' => self::VALID, 'reason' => null];
        }

        return $this->domainResult($domain);
    }

    public function isDeliverable(?string $address): bool
    {
        return $this->check($address)['status'] !== self::INVALID;
    }

    /**
     * @return array{status: string, reason: ?string}
     */
    private function domainResult(string $domain): array
    {
        $cacheKey = 'recipient-domain:'.$domain;

        if (is_array($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        $mx = $this->records($domain, DNS_MX);

        if ($mx) {
            // Null MX: a single MX whose target is "." (PHP reports it as "").
            $isNullMx = count($mx) === 1 && rtrim((string) ($mx[0]['target'] ?? ''), '.') === '';

            $result = $isNullMx
                ? $this->invalid("{$domain} doesn't accept email (it publishes a \"no mail\" record).")
                : ['status' => self::VALID, 'reason' => null];
        } elseif ($this->records($domain, DNS_A) || $this->records($domain, DNS_AAAA)) {
            $result = ['status' => self::VALID, 'reason' => null];
        } elseif (! $this->records('gmail.com', DNS_MX)) {
            // Can't even resolve gmail.com: this server's DNS is down, not the
            // recipient's domain. Don't guess, and don't cache.
            return ['status' => self::UNKNOWN, 'reason' => null];
        } else {
            $result = $this->invalid("{$domain} has no mail server (no MX record) — this address can't receive email.");
        }

        Cache::put($cacheKey, $result, $result['status'] === self::VALID ? self::CACHE_VALID_SECONDS : self::CACHE_INVALID_SECONDS);

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function records(string $host, int $type): array
    {
        return @dns_get_record($host, $type) ?: [];
    }

    /**
     * @return array{status: string, reason: string}
     */
    private function invalid(string $reason): array
    {
        return ['status' => self::INVALID, 'reason' => $reason];
    }
}
