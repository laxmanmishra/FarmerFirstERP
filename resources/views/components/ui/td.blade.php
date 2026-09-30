@props(['align' => 'left'])

<td {{ $attributes->merge(['class' => 'px-4 py-3 align-middle text-slate-700 text-'.$align]) }}>{{ $slot }}</td>
