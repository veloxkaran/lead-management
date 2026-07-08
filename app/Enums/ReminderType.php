<?php

namespace App\Enums;

enum ReminderType: string
{
    case Email = 'email';
    case Web = 'web';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Web => 'Web Notification',
        };
    }
}
