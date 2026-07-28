<?php

namespace App\Http\Controllers;

use App\Http\Requests\RawData\StoreRawDataCommentRequest;
use App\Models\RawData;
use App\Services\RawDataService;
use Illuminate\Http\RedirectResponse;

class RawDataCommentController extends Controller
{
    public function __construct(protected RawDataService $rawDataService)
    {
    }

    public function store(StoreRawDataCommentRequest $request, RawData $rawData): RedirectResponse
    {
        $this->rawDataService->addComment($rawData, $request->validated(), $request->user());

        return back()->with('success', 'Comment added successfully.');
    }
}
