@props(['label'])

<div {{ $attributes }}>
    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</dt>
    <dd class="mt-1 text-sm text-slate-800">{{ $slot->isEmpty() ? '—' : $slot }}</dd>
</div>
