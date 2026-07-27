<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Requirements - {{ config('app.name') }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .brand { font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }
        h1 { font-size: 18px; margin-top: 2px; }
        .filters { color: #6c757d; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f4f6f9; }
    </style>
</head>
<body>
    <p class="brand">{{ config('app.name') }}</p>
    <h1>Requirements</h1>
    <p>Generated {{ now()->format('M d, Y g:i A') }}</p>
    <p class="filters">
        Status: {{ collect($statuses)->first(fn ($s) => $s->value === ($filters['status'] ?? null))?->label() ?? 'All' }}
        &middot; Priority: {{ collect($priorities)->first(fn ($p) => $p->value === ($filters['priority'] ?? null))?->label() ?? 'All' }}
        &middot; {{ $requirements->count() }} {{ Str::plural('result', $requirements->count()) }}
    </p>
    <table>
        <thead>
            <tr>
                <th>Lead</th>
                <th>Requirement</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Due Date</th>
                <th>Client Acknowledged</th>
                <th>Assigned To</th>
                <th>Created By</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($requirements as $requirement)
                <tr>
                    <td>{{ $requirement->lead?->company_name ?? '—' }}</td>
                    <td>{{ $requirement->requirement }}</td>
                    <td>{{ $requirement->priority->label() }}</td>
                    <td>{{ $requirement->status->label() }}</td>
                    <td>{{ $requirement->due_date?->format('M d, Y') ?? '—' }}</td>
                    <td>{{ $requirement->client_acknowledged_at?->format('M d, Y g:i A') ?? 'Not yet' }}</td>
                    <td>{{ $requirement->assignee?->name ?? '—' }}</td>
                    <td>{{ $requirement->creator?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No requirements match the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
