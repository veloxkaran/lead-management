<?php

namespace App\Services;

use App\Enums\WhatsappMessageDirection;
use App\Enums\WhatsappMessageStatus;
use App\Models\Lead;
use App\Models\WhatsappMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Owns the domain logic of a WhatsApp webhook payload (matching a lead,
 * writing message/status rows) so WhatsappWebhookController can stay a thin
 * HTTP adapter — verify signature, delegate here, always reply 200. Kept
 * separate from the controller so this is unit-testable without an HTTP
 * request/response cycle.
 */
class WhatsappWebhookProcessor
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload): void
    {
        foreach (data_get($payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                $value = data_get($change, 'value', []);

                foreach (data_get($value, 'messages', []) as $message) {
                    $this->storeInboundMessage($message);
                }

                foreach (data_get($value, 'statuses', []) as $status) {
                    $this->updateMessageStatus($status);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function storeInboundMessage(array $message): void
    {
        $from = $this->normalizeNumber(data_get($message, 'from'));

        $lead = Lead::withoutGlobalScope('company')
            ->whereRaw("replace(replace(replace(whatsapp_number, '+', ''), ' ', ''), '-', '') = ?", [$from])
            ->first();

        if (! $lead) {
            Log::info("WhatsApp webhook: no lead found for inbound number {$from}.");

            return;
        }

        $lead->whatsappMessages()->create([
            'direction' => WhatsappMessageDirection::Inbound,
            'wa_message_id' => data_get($message, 'id'),
            'from_number' => $from,
            'to_number' => $lead->whatsapp_number,
            'type' => data_get($message, 'type', 'text'),
            'body' => data_get($message, 'text.body'),
            'status' => WhatsappMessageStatus::Received,
            'wa_timestamp' => data_get($message, 'timestamp')
                ? Carbon::createFromTimestamp((int) $message['timestamp'])
                : now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function updateMessageStatus(array $status): void
    {
        $waMessageId = data_get($status, 'id');
        $newStatus = data_get($status, 'status');

        if (! $waMessageId || ! WhatsappMessageStatus::tryFrom($newStatus)) {
            return;
        }

        WhatsappMessage::where('wa_message_id', $waMessageId)->update(['status' => $newStatus]);
    }

    private function normalizeNumber(?string $number): string
    {
        return preg_replace('/[^\d]/', '', (string) $number);
    }
}
