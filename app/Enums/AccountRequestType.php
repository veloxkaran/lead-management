<?php

namespace App\Enums;

enum AccountRequestType: string
{
    case Invoice = 'invoice';
    case Payment = 'payment';
    case Refund = 'refund';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Invoice',
            self::Payment => 'Payment',
            self::Refund => 'Refund',
            self::Other => 'Other',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Invoice => 'bg-primary',
            self::Payment => 'bg-success',
            self::Refund => 'bg-warning text-dark',
            self::Other => 'bg-secondary',
        };
    }
}
