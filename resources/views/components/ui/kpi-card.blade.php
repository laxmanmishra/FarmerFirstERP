@props([
    'label',
    'value',
    'icon' => null,
    'href' => null,
    'tone' => 'brand',
    'hint' => null,
])

@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-700',
        'amber' => 'bg-amber-50 text-amber-700',
        'rose' => 'bg-rose-50 text-rose-700',
        'sky' => 'bg-sky-50 text-sky-700',
        'slate' => 'bg-slate-100 text-slate-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge(['class' => 'group flex items-start gap-4 rounded-(--radius-card) border border-slate-200 bg-white p-4 shadow-(--shadow-card) transition '.($href ? 'hover:border-brand-300 hover:shadow-md focus-ring' : '')]) }}>
    @if ($icon)
        <span class="grid size-10 shrink-0 place-items-center rounded-lg {{ $tones[$tone] ?? $tones['brand'] }}">
            <x-ui.icon :name="$icon" class="size-5" />
        </span>
    @endif
    <div class="min-w-0">
        <p class="truncate text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</p>
        <p class="tabular mt-1 text-2xl font-semibold text-slate-900">{{ $value }}</p>
        @if ($hint)<p class="mt-0.5 text-xs text-slate-500">{{ $hint }}</p>@endif
    </div>
    @if ($href)
        <x-ui.icon name="chevron-right" class="ml-auto size-4 self-center text-slate-300 transition group-hover:text-brand-600" />
    @endif
</{{ $tag }}>
