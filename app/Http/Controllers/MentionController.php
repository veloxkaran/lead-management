<?php

namespace App\Http\Controllers;

use App\Services\MentionNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who the comment composer can suggest after "@" on a record: active
 * teammates allowed to see that record (resources/js/mentions.js).
 */
class MentionController extends Controller
{
    public function users(Request $request, MentionNotifier $mentions, string $type, int $id): JsonResponse
    {
        abort_unless(isset(MentionNotifier::SUBJECTS[$type]), 404);

        $subject = MentionNotifier::SUBJECTS[$type]::findOrFail($id);

        $this->authorize('view', $subject);

        return response()->json($mentions->mentionable($subject, $request->user()));
    }
}
