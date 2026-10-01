<?php

return [

    /*
    | Email sending speed. Campaign email goes out in batches: `batch_size`
    | emails spread at `emails_per_minute`, then a `batch_pause_minutes`
    | break before the next batch. Shared SMTP hosts flag bursts as spam and
    | most cap mail per hour, so keep the hourly total under your host's
    | limit. These are only defaults — Campaign Setup overrides them.
    */
    'batch_size' => max(1, (int) env('CAMPAIGN_BATCH_SIZE', 100)),

    'emails_per_minute' => max(1, (int) env('CAMPAIGN_EMAILS_PER_MINUTE', 20)),

    'batch_pause_minutes' => max(0, (int) env('CAMPAIGN_BATCH_PAUSE_MINUTES', 15)),

    'sms_per_minute' => max(1, (int) env('CAMPAIGN_SMS_PER_MINUTE', 60)),

    /*
    | Queued messages still unsent this many minutes after their batch was
    | queued are flagged on the campaign page — usually the queue worker
    | (cron) isn't running.
    */
    'stuck_after_minutes' => 15,

    /*
    | Country calling code stripped when normalizing phone numbers for
    | duplicate detection, so "+977 9800000000" and "9800000000" count as the
    | same number.
    */
    'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '977'),

];
