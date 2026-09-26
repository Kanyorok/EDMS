@php
    $logoPath = public_path('assets/img/psmfc.png');
    $logoData = is_file($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
@endphp
<table style="width:100%;margin-bottom:10px;border-collapse:collapse;">
    <tr>
        <td style="width:30%;text-align:left;vertical-align:middle;">
            @if ($logoData)
                <img src="{{ $logoData }}" alt="{{ config('org.name') }}" style="height:70px;">
            @endif
        </td>
        <td style="text-align:right;vertical-align:middle;font-size:12px;line-height:1.5;">
            <div style="font-size:16px;font-weight:bold;">{{ config('org.name') }}</div>
            <div>{{ config('org.address') }}</div>
            <div>Tel: {{ config('org.phone') }} | Email: {{ config('org.email') }}</div>
            <div>{{ config('org.website') }}</div>
        </td>
    </tr>
</table>
