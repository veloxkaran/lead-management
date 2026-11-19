<?php

namespace App\Http\Controllers;

use App\Http\Requests\RawData\StoreRawDataCommentRequest;
use App\Models\RawData;
use App\Services\RawDataService;
use Illuminate\Http\RedirectResponse;
use App\Services\MentionNotifier;

class RawDataCommentController extends Controller
{
    public function __construct(protected RawDataService $rawDataService)
    {
    }

    public function store(StoreRawDataCommentRequest $request, RawData $rawData, MentionNotifier $mentions): RedirectResponse
    {
        $this->rawDataService->addComment($rawData, $request->validated(), $request->user());
        $mentions->notify($request->validated('comment'), $request->user(), $rawData);

        return back()->with('success', 'Comment added successfully.');
    }
}
