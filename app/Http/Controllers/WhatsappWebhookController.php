<?php

namespace App\Http\Controllers;

use App\Services\WhatsappWebhookProcessor;
use App\Settings\WhatsappSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function verify(Request $request, WhatsappSettings $settings): Response
    {
        $verifyToken = $settings->webhookVerifyToken();

        if ($request->query('hub_verify_token') === $verifyToken && filled($verifyToken)) {
            return response($request->query('hub_challenge'), 200);
        }

        return response('Verification failed.', 403);
    }

    /**
     * Meta retries and can eventually disable a webhook that doesn't reply
     * 200 quickly, so every failure path here is logged and swallowed
     * rather than allowed to bubble up as a 4xx/5xx. Domain parsing itself
     * lives in WhatsappWebhookProcessor — this stays a thin HTTP adapter.
     */
    public function handle(Request $request, WhatsappSettings $settings, WhatsappWebhookProcessor $processor): Response
    {
        if (! $this->hasValidSignature($request, $settings)) {
            Log::warning('WhatsApp webhook received with an invalid signature.');

            return response('', 200);
        }

        try {
            $processor->process($request->all());
        } catch (\Throwable $e) {
            Log::error('Failed to process WhatsApp webhook payload: '.$e->getMessage());
        }

        return response('', 200);
    }

    private function hasValidSignature(Request $request, WhatsappSettings $settings): bool
    {
        $appSecret = $settings->appSecret();

        if (blank($appSecret)) {
            return true;
        }

        $signature = $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, $signature);
    }
}
