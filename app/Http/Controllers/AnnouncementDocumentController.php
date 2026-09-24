<?php

namespace App\Http\Controllers;

use App\Models\AnnouncementDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mirrors SupportTicketAttachmentController: served through routes (not the
 * public disk URL) so the announcement's view authorization always applies.
 */
class AnnouncementDocumentController extends Controller
{
    public function download(AnnouncementDocument $document): Response
    {
        $this->authorize('view', $document->announcement);

        return Storage::disk('public')->download($document->disk_path, $document->original_name);
    }

    /**
     * Inline disposition so the browser renders PDFs/text instead of saving them.
     */
    public function preview(AnnouncementDocument $document): Response
    {
        $this->authorize('view', $document->announcement);

        return Storage::disk('public')->response($document->disk_path, $document->original_name);
    }
}
