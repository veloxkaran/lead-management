<?php

namespace App\Http\Controllers;

use App\Models\SupportTicketAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class SupportTicketAttachmentController extends Controller
{
    /**
     * Response, not BinaryFileResponse: Storage::download() only returns a
     * BinaryFileResponse against a real filesystem disk — under a faked or
     * cloud disk it returns a StreamedResponse instead, so the narrower
     * type would break under Storage::fake() in tests.
     */
    public function download(SupportTicketAttachment $attachment): Response
    {
        $attachment->loadMissing('supportTicket');

        $this->authorize('view', $attachment->supportTicket);

        return Storage::disk('public')->download($attachment->disk_path, $attachment->original_name);
    }
}
