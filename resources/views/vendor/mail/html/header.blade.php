@props(['url'])
@php
// The slot arrives already HTML-escaped; decode so it is escaped exactly once below.
$brand = trim(html_entity_decode(strip_tags((string) $slot), ENT_QUOTES)) ?: 'PMS';
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0">
<tr>
<td width="40" height="40" align="center" valign="middle" style="width: 40px; height: 40px; background-color: #0f766e; border-radius: 10px; color: #ffffff; font-family: Arial, Helvetica, sans-serif; font-size: 20px; font-weight: bold; line-height: 40px;">{{ mb_strtoupper(mb_substr($brand, 0, 1)) }}</td>
<td style="padding-left: 10px; font-family: Arial, Helvetica, sans-serif; font-size: 18px; font-weight: bold; color: #0f172a; letter-spacing: -0.3px;">{{ $brand }}</td>
</tr>
</table>
</a>
</td>
</tr>
