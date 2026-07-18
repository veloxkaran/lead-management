<?php

namespace App\Http\Controllers;

use App\Models\ActivityLogEntry;
use App\Repositories\ActivityLogRepository;
use App\Support\ActivityLinkResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityFeedController extends Controller
{
    public function __construct(protected ActivityLogRepository $activityLog)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $page = max(1, (int) $request->integer('page', 1));

        $entries = $this->activityLog->feedForViewer($viewer, $page);

        $data = $entries->getCollection()->map(function (ActivityLogEntry $entry) use ($viewer) {
            $link = ActivityLinkResolver::resolve($entry, $viewer);

            return [
                'id' => $entry->id,
                'user_name' => $entry->user->name,
                'user_initial' => strtoupper(substr($entry->user->name, 0, 1)),
                'module' => $entry->module->value,
                'module_label' => $entry->module->label(),
                'module_icon' => $entry->module->icon(),
                'description' => $entry->description,
                'time_ago' => $entry->created_at->diffForHumans(),
                'created_at' => $entry->created_at->toIso8601String(),
                'can_view' => $link['can_view'],
                'url' => $link['url'],
            ];
        });

        return response()->json([
            'data' => $data,
            'current_page' => $entries->currentPage(),
            'last_page' => $entries->lastPage(),
            'total' => $entries->total(),
        ]);
    }
}
