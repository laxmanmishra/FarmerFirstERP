@props(['tone' => 'info', 'title' => null])

@php
    $tones = [
        'info' => ['bg-sky-50 border-sky-200 text-sky-800', 'info'],
        'success' => ['bg-emerald-50 border-emerald-200 text-emerald-800', 'check-circle'],
        'warning' => ['bg-amber-50 border-amber-200 text-amber-900', 'exclamation'],
        'danger' => ['bg-rose-50 border-rose-200 text-rose-800', 'exclamation'],
    ];
    [$classes, $icon] = $tones[$tone] ?? $tones['info'];
@endphp

<div role="alert" {{ $attributes->merge(['class' => "flex gap-3 rounded-lg border p-3.5 text-sm {$classes}"]) }}>
    <x-ui.icon :name="$icon" class="mt-0.5 size-5 shrink-0" />
    <div>
        @if ($title)<p class="font-semibold">{{ $title }}</p>@endif
        <div @class(['mt-0.5' => $title])>{{ $slot }}</div>
    </div>
</div>
