<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'department' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }
}
