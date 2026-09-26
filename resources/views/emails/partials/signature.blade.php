@php
    // Inline (CID) logo when rendered inside a Mailable, public URL otherwise (e.g. browser preview).
    $logoSrc ??= isset($message)
        ? $message->embed(public_path('assets/img/psmfc.png'))
        : asset('assets/img/psmfc.png');
    $orgName = config('org.name');
    $orgWebsite = config('org.website');
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:28px;border-top:1px solid #e5e7eb;">
    <tr>
        <td style="padding-top:20px;width:1%;vertical-align:top;padding-right:18px;">
            <img src="{{ $logoSrc }}" alt="{{ $orgName }}" width="120" style="display:block;border:0;height:auto;max-width:120px;">
        </td>
        <td style="padding-top:20px;vertical-align:top;border-left:3px solid #f28c28;padding-left:18px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;color:#4b5563;">
            <div style="font-size:14px;font-weight:bold;color:#1f2937;">{{ $orgName }}</div>
            @if (config('org.address'))
                <div>{{ config('org.address') }}</div>
            @endif
            @if (config('org.phone'))
                <div>Tel: {{ config('org.phone') }}</div>
            @endif
            @if (config('org.email'))
                <div>Email: <a href="mailto:{{ config('org.email') }}" style="color:#5a9e2f;text-decoration:none;">{{ config('org.email') }}</a></div>
            @endif
            @if ($orgWebsite)
                <div><a href="{{ $orgWebsite }}" style="color:#5a9e2f;text-decoration:none;">{{ preg_replace('#^https?://#', '', rtrim($orgWebsite, '/')) }}</a></div>
            @endif
        </td>
    </tr>
</table>
