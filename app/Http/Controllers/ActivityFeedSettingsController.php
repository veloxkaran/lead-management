<?php

namespace App\Http\Controllers;

use App\Settings\ActivityFeedSettings;
use App\Support\ActivityModules\ActivityModuleRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityFeedSettingsController extends Controller
{
    public function edit(ActivityFeedSettings $settings): View
    {
        return view('activity-feed-settings.edit', [
            'settings' => $settings->raw(),
            'modules' => ActivityModuleRegistry::definitions(),
            'enabledModules' => $settings->enabledModules(),
        ]);
    }

    public function update(Request $request, ActivityFeedSettings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'activity_feed_refresh_seconds' => ['required', 'integer', 'min:5', 'max:300'],
            'activity_feed_per_page' => ['required', 'integer', 'min:1', 'max:50'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', 'in:'.implode(',', ActivityModuleRegistry::keys())],
        ]);

        $settings->save(
            $validated['activity_feed_refresh_seconds'],
            $validated['activity_feed_per_page'],
            $validated['modules'] ?? [],
            $request->boolean('activity_feed_enabled'),
        );

        return back()->with('success', 'Activity Feed settings updated successfully.');
    }
}
