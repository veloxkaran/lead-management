<?php

namespace App\Enums;

enum TaskModule: string
{
    case Lead = 'lead';
    case Implementation = 'implementation';
    case Training = 'training';
    case Finance = 'finance';
    case Renewal = 'renewal';
    case InternalOperations = 'internal_operations';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Lead / Client',
            self::Implementation => 'Implementation',
            self::Training => 'Training',
            self::Finance => 'Finance',
            self::Renewal => 'Renewal',
            self::InternalOperations => 'Internal Operations',
        };
    }
}
