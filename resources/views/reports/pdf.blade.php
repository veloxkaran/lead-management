<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ config('app.name') }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .brand { font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }
        h1 { font-size: 18px; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f4f6f9; }
    </style>
</head>
<body>
    <p class="brand">{{ config('app.name') }}</p>
    <h1>{{ $title }}</h1>
    <p>Generated {{ now()->format('M d, Y g:i A') }}</p>
    <table>
        <thead><tr>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ((array) $row as $value)<td>{{ $value }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($headings) }}">No data</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
