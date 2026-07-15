<?php

namespace App\Jobs;

use App\Enums\WhatsappMessageStatus;
use App\Models\WhatsappMessage;
use App\Support\WhatsappClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsappMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(protected WhatsappMessage $message)
    {
    }

    public function handle(WhatsappClient $client): void
    {
        try {
            $response = $this->message->type === 'template'
                ? $client->sendTemplate(
                    $this->message->to_number,
                    $this->message->template_name,
                    data_get($this->message->template_payload, 'language', 'en_US'),
                    data_get($this->message->template_payload, 'components', []),
                )
                : $client->sendText($this->message->to_number, $this->message->body);

            $this->message->update([
                'status' => WhatsappMessageStatus::Sent,
                'wa_message_id' => data_get($response, 'messages.0.id'),
            ]);
        } catch (\Throwable $e) {
            $this->message->update([
                'status' => WhatsappMessageStatus::Failed,
                'status_error' => $e->getMessage(),
            ]);
        }
    }
}
