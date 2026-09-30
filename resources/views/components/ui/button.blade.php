@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'type' => 'button',
])

@php
    $variants = [
        'primary' => 'bg-brand-700 text-white shadow-xs hover:bg-brand-800 active:bg-brand-900',
        'secondary' => 'bg-white text-slate-700 ring-1 ring-inset ring-slate-300 shadow-xs hover:bg-slate-50',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
        'danger' => 'bg-rose-600 text-white shadow-xs hover:bg-rose-700',
        'danger-ghost' => 'text-rose-600 hover:bg-rose-50',
    ];
    $sizes = [
        'xs' => 'h-7 gap-1 rounded-md px-2 text-xs',
        'sm' => 'h-8 gap-1.5 rounded-lg px-3 text-sm',
        'md' => 'h-9 gap-2 rounded-lg px-3.5 text-sm',
        'lg' => 'h-11 gap-2 rounded-lg px-5 text-base',
    ];
    $classes = 'focus-ring inline-flex shrink-0 items-center justify-center font-medium transition-colors disabled:pointer-events-none disabled:opacity-60 '
        .($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
    $iconClass = $size === 'xs' ? 'size-3.5' : 'size-4';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" :class="$iconClass" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($attributes->has('wire:click') || $type === 'submit')
            <svg wire:loading wire:target="{{ $attributes->get('wire:target', $attributes->get('wire:click')) }}" class="{{ $iconClass }} animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
            </svg>
        @endif
        @if ($icon)<x-ui.icon :name="$icon" :class="$iconClass" />@endif
        {{ $slot }}
    </button>
@endif
