<?php

namespace App\Http\Controllers;

use App\Enums\EmailLogStatus;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['status', 'search']);

        $query = EmailLog::with('related')->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('to_email', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%"));
        }

        return view('email-logs.index', [
            'logs' => $query->paginate(20)->withQueryString(),
            'statuses' => EmailLogStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function show(EmailLog $emailLog): View
    {
        return view('email-logs.show', ['log' => $emailLog]);
    }
}
