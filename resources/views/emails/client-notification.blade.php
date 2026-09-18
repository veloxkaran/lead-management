@php
    $companyName = \App\Models\Setting::get('company_name') ?: config('app.name');
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7; padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="background-color:#2456a6; padding: 20px 32px; color:#ffffff; font-size: 18px; font-weight: 600;">
                            {{ $companyName }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px; color:#1f2937; font-size: 14px; line-height: 1.6;">
                            {!! nl2br(e($body)) !!}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 16px 32px; color:#9ca3af; font-size: 12px; border-top: 1px solid #e5e7eb;">
                            This is an automated message from {{ $companyName }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
