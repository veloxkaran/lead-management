<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Marks a single database notification read and redirects to whatever
     * it links to. Scoped to the authenticated user's own notifications
     * (findOrFail on their relation) rather than a global lookup by id,
     * since DatabaseNotification's route-model binding isn't ownership-aware.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->findOrFail($notification);

        $record->markAsRead();

        return redirect($record->data['url'] ?? route('dashboard'));
    }
}
