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
{{-- Deliberately plain: mostly text, one column, no remote images except the
     optional open-tracking pixel — image-heavy, link-heavy layouts score as
     spam. width="600" is for Outlook for Windows, which ignores max-width. --}}
<body style="margin:0; padding:0; background-color:#f6f7f9; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f6f7f9; padding: 24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; background-color:#ffffff; border:1px solid #e5e7eb; border-radius: 6px;">
                    <tr>
                        <td style="padding: 28px 32px 8px; color:#1f2937; font-size: 15px; line-height: 1.6;">
                            {!! nl2br(e($body)) !!}
                            {{-- Embedded (cid:) with its real type — a generic octet-stream part isn't shown as an image by every mail app.
                                 width="" is for Outlook for Windows. --}}
                            @foreach ($images as $image)
                                <div style="margin-top: 16px;">
                                    <img src="{{ isset($message) ? $message->embedData((string) @file_get_contents($image['path']), $image['name'], $image['mime']) : 'data:'.$image['mime'].';base64,'.base64_encode((string) @file_get_contents($image['path'])) }}" width="{{ $image['width'] }}" alt="" style="width: 100%; max-width: {{ $image['width'] }}px; height: auto; border: 0; border-radius: 4px; display: block;">
                                </div>
                            @endforeach
                            @if ($documents)
                                <div style="margin-top: 16px; color:#6b7280; font-size: 13px;">
                                    Attached: {{ collect($documents)->pluck('name')->implode(', ') }}
                                </div>
                            @endif
                        </td>
                    </tr>
                    @if ($signatureHtml)
                        <tr>
                            <td style="padding: 8px 32px 24px; color:#374151; font-size: 14px; line-height: 1.5;">
                                {{-- Signature images are embedded inline (cid:) while mailing; see CampaignSignature. --}}
                                {!! \App\Support\CampaignSignature::forEmail($signatureHtml, $message ?? null) !!}
                            </td>
                        </tr>
                    @endif
                </table>
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%; max-width:600px;">
                    <tr>
                        <td style="padding: 16px 32px; color:#6b7280; font-size: 12px; line-height: 1.5; text-align:center;">
                            @if ($footer)
                                {!! nl2br(e($footer)) !!}<br>
                            @else
                                {{ $companyName }}<br>
                            @endif
                            @if ($unsubscribeUrl)
                                Don't want these emails? <a href="{{ $unsubscribeUrl }}" style="color:#6b7280; text-decoration: underline;">Unsubscribe</a>.
                            @endif
                            @if (! empty($trackingUrl))
                                <img src="{{ $trackingUrl }}" width="1" height="1" alt="" style="display:block; width:1px; height:1px; border:0; margin: 0 auto;">
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
