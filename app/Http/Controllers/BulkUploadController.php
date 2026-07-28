<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class BulkUploadController extends Controller
{
    /**
     * Just a navigational hub linking to each resource's own bulk-upload
     * flow — each destination still enforces its own policy, so this page
     * itself doesn't need a separate authorization check.
     */
    public function index(): View
    {
        return view('bulk-upload.index');
    }
}
