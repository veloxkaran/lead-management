<?php

namespace App\Http\Controllers;

use App\Settings\WhatsappSettings;
use App\Support\WhatsappClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsappSettingsController extends Controller
{
    public function edit(WhatsappSettings $settings): View
    {
        return view('whatsapp-settings.edit', ['settings' => $settings->all()]);
    }

    public function update(Request $request, WhatsappSettings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:255'],
            'whatsapp_business_account_id' => ['nullable', 'string', 'max:255'],
            'whatsapp_access_token' => ['nullable', 'string'],
            'whatsapp_webhook_verify_token' => ['nullable', 'string', 'max:255'],
            'whatsapp_app_secret' => ['nullable', 'string', 'max:255'],
            'whatsapp_app_id' => ['nullable', 'string', 'max:255'],
        ]);

        $settings->save($validated, $request->boolean('whatsapp_enabled'));

        return back()->with('success', 'WhatsApp settings updated successfully.');
    }

    public function test(Request $request, WhatsappClient $client): RedirectResponse
    {
        $validated = $request->validate([
            'test_number' => ['required', 'string', 'max:30'],
        ]);

        try {
            $client->sendText($validated['test_number'], 'This is a test message from your lead management system.');

            return back()->with('success', 'Test message sent successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to send test message: '.$e->getMessage());
        }
    }
}
