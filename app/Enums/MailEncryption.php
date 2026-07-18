<?php

namespace App\Enums;

enum MailEncryption: string
{
    case None = 'none';
    case Ssl = 'ssl';
    case Tls = 'tls';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Ssl => 'SSL',
            self::Tls => 'TLS',
        };
    }
}
