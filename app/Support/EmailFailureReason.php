<?php

namespace App\Support;

/**
 * Turns a raw mail/gateway error ("Expected response code 235 but got code
 * 535 …") into a plain reason someone can act on — shown as the remark on
 * the Email Log and the campaign send log, with the raw error kept below.
 */
class EmailFailureReason
{
    /** [pattern, reason] — first match wins, so the most specific come first. */
    private const RULES = [
        ['/\b535\b|authenticat/i', 'The mail server rejected the login — the sending username or password is wrong (or the account is temporarily blocked).'],
        ['/\b5\.1\.1\b|user unknown|no such user|does not exist|recipient.*(rejected|unknown|invalid)|mailbox unavailable|\b550\b.*(mailbox|user|recipient)/i', 'The recipient address doesn\'t exist — check it for typos.'],
        ['/message.*(too large|size)|exceeds.*size|size limit/i', 'The email is too large for the receiving server — use smaller attachments.'],
        ['/\b552\b|mailbox full|quota|over quota|insufficient storage/i', 'The recipient\'s mailbox is full.'],
        ['/\b55[34]\b.*(spam|block|blacklist|reputation|policy)|spam|blocked|blacklist|blocklist/i', 'The receiving server refused it as spam or blocked the sender — check SPF/DKIM/DMARC in Campaign Setup.'],
        ['/\b553\b|sender.*(not allowed|rejected|denied)|not permitted to send|from address/i', 'The mail server refused the From address — it must be a mailbox the login is allowed to send as.'],
        ['/\b(421|450|451|452)\b|try again later|temporar|rate limit|too many/i', 'The server is busy or limiting how much can be sent — it may work if retried later.'],
        ['/timed? ?out|timeout/i', 'The mail server didn\'t answer in time.'],
        ['/could not be established|connection refused|unable to connect|getaddrinfo|name or service not known|network is unreachable/i', 'Couldn\'t reach the mail server — check the server name and port.'],
        ['/ssl|tls|certificate|crypto/i', 'The secure connection to the mail server failed — check the encryption setting (SSL for 465, TLS for 587).'],
        ['/gateway delivery report/i', 'The SMS gateway reported it as not delivered.'],
        ['/sms gateway.*not set up|not set up/i', 'The SMS gateway isn\'t set up yet.'],
    ];

    public static function explain(?string $error): ?string
    {
        $error = trim((string) $error);

        if ($error === '') {
            return null;
        }

        foreach (self::RULES as [$pattern, $reason]) {
            if (preg_match($pattern, $error)) {
                return $reason;
            }
        }

        return null;
    }
}
