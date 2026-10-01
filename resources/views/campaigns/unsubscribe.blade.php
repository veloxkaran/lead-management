{{-- Public page (no session, no app layout) — opened from the unsubscribe link in a campaign email. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Unsubscribe · {{ $companyName }}</title>
    <style>
        :root { --bg: #f6f7f9; --card: #ffffff; --text: #1f2937; --muted: #6b7280; --border: #e5e7eb; --accent: #2456a6; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #111827; --card: #1f2937; --text: #f3f4f6; --muted: #9ca3af; --border: #374151; --accent: #7aa2e3; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; background: var(--bg); color: var(--text); font: 15px/1.6 system-ui, -apple-system, "Segoe UI", Arial, sans-serif; }
        .card { width: 100%; max-width: 440px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 28px; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        p { margin: 0 0 16px; color: var(--muted); }
        strong { color: var(--text); word-break: break-all; }
        button { width: 100%; padding: 10px 16px; border: 0; border-radius: 6px; background: var(--accent); color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        .company { font-size: 13px; color: var(--muted); margin-bottom: 16px; }
    </style>
</head>
<body>
    <main class="card">
        <div class="company">{{ $companyName }}</div>

        @if (! $recipient)
            <h1>Link not recognised</h1>
            <p>This unsubscribe link isn't valid — it may be from a test email. If you keep getting emails you don't want, reply to one of them and ask to be removed.</p>
        @elseif ($done)
            <h1>You're unsubscribed</h1>
            <p><strong>{{ $recipient->address }}</strong> won't receive any more campaign emails from {{ $companyName }}.</p>
        @else
            <h1>Unsubscribe?</h1>
            <p>Stop campaign emails from {{ $companyName }} to <strong>{{ $recipient->address }}</strong>.</p>
            <form method="POST" action="{{ route('campaigns.unsubscribe', $token) }}">
                <button type="submit">Unsubscribe</button>
            </form>
        @endif
    </main>
</body>
</html>
