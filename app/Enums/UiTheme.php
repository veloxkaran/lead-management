<?php

namespace App\Enums;

/**
 * The colour themes a user can pick from the top bar. Stored on
 * users.theme; null there means DEFAULT.
 */
enum UiTheme: string
{
    case LightBlue = 'light-blue';
    case Light = 'light';
    case Dark = 'dark';

    public const DEFAULT = self::LightBlue;

    public function label(): string
    {
        return match ($this) {
            self::LightBlue => 'Light Blue',
            self::Light => 'Light',
            self::Dark => 'Dark',
        };
    }

    /**
     * The swatch shown next to the option in the theme picker.
     */
    public function swatch(): string
    {
        return match ($this) {
            self::LightBlue => '#dbe8fb',
            self::Light => '#ffffff',
            self::Dark => '#1b2433',
        };
    }

    /**
     * Bootstrap's own colour mode (data-bs-theme) for this theme.
     */
    public function colorMode(): string
    {
        return $this === self::Dark ? 'dark' : 'light';
    }
}
