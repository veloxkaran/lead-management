<?php

namespace App\Http\Controllers;

use App\Models\PolicyDocumentAcknowledgment;
use App\Models\PolicyDocumentVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;

class PolicyDocumentAcknowledgmentController extends Controller
{
    public function view(Request $request, PolicyDocumentVersion $policy_document_version): JsonResponse
    {
        $this->authorize('view', $policy_document_version->policyDocument);

        $acknowledgment = $this->findOrCreate($request, $policy_document_version);

        if (! $acknowledgment->viewed_at) {
            $acknowledgment->update(['viewed_at' => now(), ...$this->requestMeta($request)]);
        }

        return response()->json(['viewed_at' => $acknowledgment->viewed_at]);
    }

    public function acknowledge(Request $request, PolicyDocumentVersion $policy_document_version): JsonResponse
    {
        $this->authorize('view', $policy_document_version->policyDocument);

        $acknowledgment = $this->findOrCreate($request, $policy_document_version);

        $acknowledgment->update([
            'viewed_at' => $acknowledgment->viewed_at ?? now(),
            'acknowledged_at' => now(),
            ...$this->requestMeta($request),
        ]);

        return response()->json(['acknowledged_at' => $acknowledgment->acknowledged_at]);
    }

    private function findOrCreate(Request $request, PolicyDocumentVersion $version): PolicyDocumentAcknowledgment
    {
        return PolicyDocumentAcknowledgment::firstOrCreate([
            'policy_document_version_id' => $version->id,
            'user_id' => $request->user()->id,
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function requestMeta(Request $request): array
    {
        $agent = new Agent;
        $agent->setUserAgent($request->userAgent());

        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device' => match (true) {
                $agent->isTablet() => 'Tablet',
                $agent->isMobile() => 'Mobile',
                default => 'Desktop',
            },
            'browser' => $agent->browser() ?: null,
        ];
    }
}
