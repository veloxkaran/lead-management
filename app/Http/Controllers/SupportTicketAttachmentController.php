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

    /**
     * Same authorization as download(), but served with an inline
     * disposition so the browser renders it (image/PDF) instead of saving
     * it — deliberately not just linking straight to
     * SupportTicketAttachment::url()'s public disk URL, which would bypass
     * this authorization check entirely.
     */
    public function preview(SupportTicketAttachment $attachment): Response
    {
        $attachment->loadMissing('supportTicket');

        $this->authorize('view', $attachment->supportTicket);

        return Storage::disk('public')->response($attachment->disk_path, $attachment->original_name);
    }
}
