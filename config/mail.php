<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Links Inside Emails & Open Tracking
    |--------------------------------------------------------------------------
    |
    | links_url: where the unsubscribe link and tracking image point. Leave
    | empty to use APP_URL. Set it to an HTTPS host on the sender's own
    | domain (e.g. https://track.hajirhr.com, pointed at this app with a
    | valid SSL certificate) so links match the From address — Gmail
    | distrusts links to another domain or over plain http.
    |
    | track_notification_opens: adds an invisible 1×1 image to client
    | notification emails (Email Log) that marks them "Delivered" when
    | opened. Off by default — tracking images are a spam signal. Even when
    | on, the image is only added if the links URL above is HTTPS. Campaign
    | emails have their own "Track opens" switch in Campaign Setup, with the
    | same HTTPS requirement.
    |
    */

    'links_url' => env('MAIL_LINKS_URL'),

    'track_notification_opens' => (bool) env('MAIL_TRACK_NOTIFICATION_OPENS', false),

    /*
    |--------------------------------------------------------------------------
    | Announcement Send Rate
    |--------------------------------------------------------------------------
    |
    | Announcement emails are queued with staggered delays so no more than
    | this many go out per minute — shared hosts (cPanel) cap outgoing mail
    | per hour and suspend sending past it. 8/min ≈ 480/hour.
    |
    */

    'announcement_per_minute' => max(1, (int) env('ANNOUNCEMENT_EMAILS_PER_MINUTE', 8)),

    // Before sending a campaign/announcement email, look up the recipient's
    // domain in DNS and skip addresses whose domain can't receive mail
    // (App\Support\RecipientEmailCheck). Format and typo checks always run.
    'verify_recipient_domains' => (bool) env('MAIL_VERIFY_RECIPIENT_DOMAINS', true),

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

];
