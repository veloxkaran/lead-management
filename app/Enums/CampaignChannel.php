<?php

namespace App\Enums;

enum CampaignChannel: string
{
    case Email = 'email';
    case Sms = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Sms => 'SMS',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Email => 'bi-envelope',
            self::Sms => 'bi-chat-dots',
        };
    }
}
