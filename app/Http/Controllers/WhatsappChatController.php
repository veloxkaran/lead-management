<?php

namespace App\Http\Controllers;

use App\Enums\WhatsappMessageDirection;
use App\Enums\WhatsappMessageStatus;
use App\Jobs\SendWhatsappMessage;
use App\Models\Lead;
use App\Support\WhatsappClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsappChatController extends Controller
{
    public function index(Request $request): View
    {
        return view('whatsapp.index', [
            'leads' => $this->visibleLeads($request)->get(),
            'activeLead' => null,
        ]);
    }

    public function show(Request $request, Lead $lead): View
    {
        $this->authorize('chatWhatsapp', $lead);

        $lead->load(['whatsappUsers']);
        $lead->setRelation(
            'whatsappMessages',
            $lead->whatsappMessages()->latest()->limit(50)->get()->reverse()->values()
        );

        return view('whatsapp.index', [
            'leads' => $this->visibleLeads($request)->get(),
            'activeLead' => $lead,
        ]);
    }

    public function messages(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('chatWhatsapp', $lead);

        $validated = $request->validate(['after_id' => ['nullable', 'integer']]);

        $messages = $lead->whatsappMessages()
            ->when($validated['after_id'] ?? null, fn ($q, $afterId) => $q->where('id', '>', $afterId))
            ->get();

        return response()->json([
            'messages' => $messages,
            'window_open' => $lead->isWhatsappWindowOpen(),
        ]);
    }

    public function sendMessage(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('chatWhatsapp', $lead);

        if (! $lead->isWhatsappWindowOpen()) {
            return response()->json([
                'message' => 'The 24-hour reengagement window is closed. Send a template message to reopen the conversation.',
            ], 422);
        }

        $validated = $request->validate(['body' => ['required', 'string']]);

        $message = $lead->whatsappMessages()->create([
            'direction' => WhatsappMessageDirection::Outbound,
            'to_number' => $lead->whatsapp_number,
            'type' => 'text',
            'body' => $validated['body'],
            'status' => WhatsappMessageStatus::Queued,
            'sent_by' => $request->user()->id,
        ]);

        SendWhatsappMessage::dispatch($message);

        return response()->json(['message' => $message]);
    }

    public function templates(WhatsappClient $client): JsonResponse
    {
        return response()->json(['templates' => $client->fetchTemplates()]);
    }

    public function sendTemplate(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('chatWhatsapp', $lead);

        $validated = $request->validate([
            'template_name' => ['required', 'string'],
            'language' => ['required', 'string'],
            'components' => ['nullable', 'array'],
        ]);

        $message = $lead->whatsappMessages()->create([
            'direction' => WhatsappMessageDirection::Outbound,
            'to_number' => $lead->whatsapp_number,
            'type' => 'template',
            'template_name' => $validated['template_name'],
            'template_payload' => [
                'language' => $validated['language'],
                'components' => $validated['components'] ?? [],
            ],
            'status' => WhatsappMessageStatus::Queued,
            'sent_by' => $request->user()->id,
        ]);

        SendWhatsappMessage::dispatch($message);

        return response()->json(['message' => $message]);
    }

    private function visibleLeads(Request $request)
    {
        $user = $request->user();

        $query = Lead::query()->whereNotNull('whatsapp_number');

        if (! $user->isSuperAdmin()) {
            $query->whereHas('whatsappUsers', fn ($q) => $q->where('user_id', $user->id));
        }

        return $query->with('lastInboundWhatsappMessage')
            ->withMax('whatsappMessages as last_message_at', 'created_at')
            ->orderByDesc('last_message_at');
    }
}
