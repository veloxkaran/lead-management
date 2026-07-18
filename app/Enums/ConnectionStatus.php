<?php

namespace App\Enums;

enum ConnectionStatus: string
{
    case NotTested = 'not_tested';
    case Connected = 'connected';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NotTested => 'Not Tested',
            self::Connected => 'Connected',
            self::Failed => 'Failed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NotTested => 'bg-secondary',
            self::Connected => 'bg-success',
            self::Failed => 'bg-danger',
        };
    }
}
