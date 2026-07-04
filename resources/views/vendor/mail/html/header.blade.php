@props(['url'])
<tr>
<td class="header">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="left" style="vertical-align: middle;">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<span style="display: inline-block; width: 36px; height: 36px; border-radius: 10px; background: #2563eb; color: #ffffff; font-size: 18px; font-weight: 700; line-height: 36px; text-align: center; margin-right: 10px;">D</span>
<span style="font-size: 22px; font-weight: 700; color: #0f172a; vertical-align: middle;">Docflow</span>
</a>
</td>
<td align="right" style="vertical-align: middle; font-size: 12px; color: #64748b; line-height: 1.4;">
<strong style="display: block; color: #475569; font-size: 11px; font-weight: 600;">Gerado em</strong>
{{ now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
</td>
</tr>
</table>
</td>
</tr>
