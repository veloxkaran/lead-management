<?php

namespace App\Enums;

enum WhatsappMessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';

    /** Inbound-only — Meta's status webhook only ever reports on outbound sends. */
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Read => 'Read',
            self::Failed => 'Failed',
            self::Received => 'Received',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Queued => 'bi-clock',
            self::Sent => 'bi-check',
            self::Delivered => 'bi-check2-all',
            self::Read => 'bi-check2-all text-primary',
            self::Failed => 'bi-exclamation-circle text-danger',
            self::Received => 'bi-arrow-down-circle',
        };
    }
}
