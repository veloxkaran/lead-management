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
        /* Fixed layout + explicit widths: without them Dompdf sizes columns to
           content, and long requirement text pushed the last columns off the page. */
        table { width: 100%; border-collapse: collapse; margin-top: 12px; table-layout: fixed; }
        th, td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; }
        th { background: #f4f6f9; font-size: 11px; }
        td { font-size: 11px; }
        td p { margin: 0 0 4px; }
        td img { max-width: 100%; height: auto; }
        .status { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; white-space: nowrap; }
        .status-pending { background: #e9ecef; color: #495057; }
        .status-in_progress { background: #cfe2ff; color: #084298; }
        .status-in_review { background: #cff4fc; color: #055160; }
        .status-completed { background: #d1e7dd; color: #0f5132; }
        .status-on_hold { background: #fff3cd; color: #664d03; }
    </style>
</head>
<body>
    <p class="brand">{{ config('app.name') }}</p>
    <h1>Requirements</h1>
    <p>Generated {{ now()->format('M d, Y g:i A') }}</p>
    <p class="filters">
        Company: {{ $filters['search'] ?? 'All' }}
        &middot; Status: {{ collect($statuses)->first(fn ($s) => $s->value === ($filters['status'] ?? null))?->label() ?? 'All' }}
        &middot; Priority: {{ collect($priorities)->first(fn ($p) => $p->value === ($filters['priority'] ?? null))?->label() ?? 'All' }}
        &middot; Sprint: {{ $filters['sprint'] ?? 'All' }}
        &middot; {{ $requirements->count() }} {{ Str::plural('result', $requirements->count()) }}
    </p>
    <table>
        <thead>
            <tr>
                <th style="width: 11%;">Lead</th>
                <th style="width: 10%;">Title</th>
                <th style="width: 27%;">Requirement</th>
                <th style="width: 7%;">Priority</th>
                <th style="width: 9%;">Status</th>
                <th style="width: 8%;">Due Date</th>
                <th style="width: 10%;">Client Acknowledged</th>
                <th style="width: 9%;">Assigned To</th>
                <th style="width: 9%;">Created By</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($requirements as $requirement)
                <tr>
                    <td>{{ $requirement->lead?->company_name ?? '—' }}</td>
                    <td>{{ $requirement->title ?? '—' }}</td>
                    <td>{!! $requirement->requirementHtml() !!}</td>
                    <td>{{ $requirement->priority->label() }}</td>
                    <td><span class="status status-{{ $requirement->status->value }}">{{ $requirement->status->label() }}</span></td>
                    <td>{{ $requirement->due_date?->format('M d, Y') ?? '—' }}</td>
                    <td>{{ $requirement->client_acknowledged_at?->format('M d, Y g:i A') ?? 'Not yet' }}</td>
                    <td>{{ $requirement->assignee?->name ?? '—' }}</td>
                    <td>{{ $requirement->creator?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9">No requirements match the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
