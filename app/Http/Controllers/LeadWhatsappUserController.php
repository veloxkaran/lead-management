<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadWhatsappUserController extends Controller
{
    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('manageWhatsappUsers', $lead);

        $validated = $request->validate([
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $lead->whatsappUsers()->sync($validated['user_ids'] ?? []);

        return back()->with('success', 'WhatsApp access updated.');
    }
}
