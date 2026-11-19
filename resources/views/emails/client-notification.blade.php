@php
    $companyName = \App\Models\Setting::get('company_name') ?: config('app.name');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $companyName }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7; padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="background-color:#2456a6; padding: 20px 32px; color:#ffffff; font-size: 18px; font-weight: 600;">
                            {{ $companyName }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px; color:#1f2937; font-size: 14px; line-height: 1.6;">
                            {!! nl2br(e($body)) !!}
                            {{-- $message only exists while actually mailing. width="" is for Outlook for Windows, which ignores CSS max-width. --}}
                            @foreach ($images ?? [] as $image)
                                <div style="margin-top: 16px;">
                                    <img src="{{ isset($message) ? $message->embed($image['path']) : $image['path'] }}" width="{{ $image['width'] }}" alt="" style="width: 100%; max-width: {{ $image['width'] }}px; height: auto; border: 0; border-radius: 4px; display: block;">
                                </div>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 16px 32px; color:#6b7280; font-size: 12px; line-height: 1.5; border-top: 1px solid #e5e7eb;">
                            {{-- Footer (company name/address) and unsubscribe: required for bulk mail (announcements). --}}
                            @if (! empty($footer))
                                {!! nl2br(e($footer)) !!}<br>
                            @else
                                {{ $companyName }}<br>
                            @endif
                            @if (! empty($unsubscribeUrl))
                                You're receiving this because you're a contact of {{ $companyName }}.
                                <a href="{{ $unsubscribeUrl }}" style="color:#6b7280; text-decoration: underline;">Unsubscribe</a>.
                            @endif
                            @if (! empty($trackingUrl))
                                <img src="{{ $trackingUrl }}" width="1" height="1" alt="" style="display:block; width:1px; height:1px; border:0;">
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
