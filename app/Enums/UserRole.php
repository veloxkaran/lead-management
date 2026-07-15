<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Manager = 'manager';
    case BusinessDevelopment = 'business_development';
    case CustomerSuccess = 'customer_success';
    case Finance = 'finance';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Manager => 'Manager',
            self::BusinessDevelopment => 'Business Development',
            self::CustomerSuccess => 'Customer Success',
            self::Finance => 'Finance',
        };
    }
}
