<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    protected const KEYS = [
        'slack_webhook_url',
        'google_drive_folder_link',
        'company_name',
        'company_email',
        'company_phone',
        'company_address',
        'motivation_quote_api_url',
        'google_meet_default_link',
        'notifications_email_enabled',
        'notifications_web_enabled',
        'dashboard_show_motivation_quote',
    ];

    public function edit(): View
    {
        $settings = collect(self::KEYS)->mapWithKeys(fn ($key) => [$key => Setting::get($key)]);

        return view('settings.edit', ['settings' => $settings]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slack_webhook_url' => ['nullable', 'url'],
            'google_drive_folder_link' => ['nullable', 'url'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:30'],
            'company_address' => ['nullable', 'string'],
            'motivation_quote_api_url' => ['nullable', 'url'],
            'google_meet_default_link' => ['nullable', 'url'],
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value);
        }

        Setting::set('notifications_email_enabled', $request->boolean('notifications_email_enabled') ? '1' : '0');
        Setting::set('notifications_web_enabled', $request->boolean('notifications_web_enabled') ? '1' : '0');
        Setting::set('dashboard_show_motivation_quote', $request->boolean('dashboard_show_motivation_quote') ? '1' : '0');

        return back()->with('success', 'Settings updated successfully.');
    }
}
