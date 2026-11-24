<?php

namespace App\Enums;

/**
 * What kind of support ticket it is. Existing tickets raised before types
 * existed have none (null) and show as "Not set".
 */
enum SupportTicketType: string
{
    case SystemBug = 'system_bug';
    case Support = 'support';
    case Training = 'training';
    case FeatureEnhancement = 'feature_enhancement';

    public function label(): string
    {
        return match ($this) {
            self::SystemBug => 'System Bug',
            self::Support => 'Support',
            self::Training => 'Training',
            self::FeatureEnhancement => 'Feature Enhancement',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SystemBug => 'bi-bug',
            self::Support => 'bi-headset',
            self::Training => 'bi-mortarboard',
            self::FeatureEnhancement => 'bi-stars',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::SystemBug => 'bg-danger',
            self::Support => 'bg-primary',
            self::Training => 'bg-info',
            self::FeatureEnhancement => 'bg-success',
        };
    }
}
