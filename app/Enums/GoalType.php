<?php

namespace App\Enums;

enum GoalType: string
{
    case Organization = 'organization';
    case Team = 'team';
    case Individual = 'individual';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
