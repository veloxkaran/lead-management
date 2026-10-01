<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Proof that a campaign was previewed exactly as it's being sent. The
 * compose-preview endpoint signs a fingerprint of everything that changes
 * what goes out (message, subject, recipients, schedule, signature, the
 * attached files' contents);
 * storing the campaign recomputes it from the submitted form and refuses
 * a missing or different one — so skipping the preview, or editing after
 * it, means previewing again. The campaign name (internal only) is left
 * out, so renaming doesn't.
 */
class CampaignPreviewToken
{
    private const FIELDS = [
        'channel', 'subject', 'message', 'audience', 'lead_status_ids', 'industries', 'lead_ids',
        'all_contacts', 'contact_ids', 'extra_contacts', 'scheduled_at', 'include_signature',
    ];

    public static function for(Request $request): string
    {
        $data = [];

        foreach (self::FIELDS as $field) {
            $data[$field] = self::normalize($request->input($field));
        }

        // The files themselves, by content — a different file with the same name doesn't pass.
        $data['attachments'] = collect($request->file('attachments', []))
            ->filter(fn ($file) => $file?->isValid())
            ->map(fn ($file) => sha1_file($file->getRealPath()).':'.$file->getClientOriginalName())
            ->sort()->values()->all();

        return hash_hmac('sha256', json_encode($data).'|'.$request->user()?->id, (string) config('app.key'));
    }

    public static function matches(Request $request, mixed $token): bool
    {
        return is_string($token) && hash_equals(self::for($request), $token);
    }

    /**
     * Strings trimmed; lists compared as sorted sets of strings, so the
     * order select2 sends them in doesn't matter.
     */
    private static function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            $values = array_map(fn ($v) => trim((string) $v), array_values($value));
            sort($values);

            return $values;
        }

        return $value === null ? '' : trim((string) $value);
    }
}
