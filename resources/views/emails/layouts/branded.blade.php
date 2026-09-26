@php
    $logoSrc = isset($message)
        ? $message->embed(public_path('assets/img/psmfc.png'))
        : asset('assets/img/psmfc.png');
    $orgName = config('org.name');
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $title ?? $orgName }}</title>
    <style>
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; }
        img { -ms-interpolation-mode: bicubic; }
        a { color: #5a9e2f; }
        .email-body p { margin: 0 0 14px; }
        .email-body table { max-width: 100%; }
        @media only screen and (max-width: 620px) {
            .email-card { width: 100% !important; }
            .email-pad { padding: 24px 20px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;">
@hasSection('preheader')
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">@yield('preheader')</div>
@endif
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f3f4f6;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" border="0" width="620" style="width:620px;max-width:100%;background-color:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
                {{-- Brand header --}}
                <tr>
                    <td style="padding:16px 32px;border-bottom:4px solid #f28c28;background-color:#ffffff;">
                        <span style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:bold;letter-spacing:.06em;text-transform:uppercase;color:#5a9e2f;">{{ $orgName }}</span>
                    </td>
                </tr>
                {{-- Content --}}
                <tr>
                    <td class="email-pad email-body" style="padding:32px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.65;color:#1f2937;">
                        @yield('content')
                        @hasSection('signature')
                            @yield('signature')
                        @endif
                        @include('emails.partials.signature', ['logoSrc' => $logoSrc])
                    </td>
                </tr>
                {{-- Footer --}}
                <tr>
                    <td style="padding:18px 32px;background-color:#f9fafb;border-top:1px solid #e5e7eb;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#6b7280;text-align:center;">
                        This message and any attachments are confidential and intended solely for the addressee.
                        If you received it in error, please notify the sender and delete it.<br>
                        &copy; {{ date('Y') }} {{ $orgName }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
