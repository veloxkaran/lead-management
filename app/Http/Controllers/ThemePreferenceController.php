<?php

namespace App\Http\Controllers;

use App\Enums\UiTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class ThemePreferenceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', new Enum(UiTheme::class)],
        ]);

        // Not mass-assignable: only this endpoint changes it.
        $request->user()->forceFill(['theme' => $validated['theme']])->save();

        return back();
    }
}
