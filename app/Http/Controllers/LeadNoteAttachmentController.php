<?php

namespace App\Http\Controllers;

use App\Models\LeadNoteAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeadNoteAttachmentController extends Controller
{
    public function download(LeadNoteAttachment $attachment): BinaryFileResponse
    {
        $attachment->loadMissing('note.lead');

        $this->authorize('view', $attachment->note->lead);

        return Storage::disk('public')->download($attachment->disk_path, $attachment->original_name);
    }
}
