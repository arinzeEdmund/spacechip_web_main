@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Spacechip')
<div style="height: 48px; width: 48px; border-radius: 12px; background: linear-gradient(90deg, #f27457, #145454); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-family: 'Instrument Sans', sans-serif; font-size: 24px;">
    SC
</div>
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
