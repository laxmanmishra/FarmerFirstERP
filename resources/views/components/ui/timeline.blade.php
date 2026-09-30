@props(['items' => []])

{{-- $items: list of ['at' => Carbon, 'title' => string, 'body' => ?string, 'actor' => ?string, 'tone' => string, 'icon' => string] --}}
@php
    $tones = ['brand' => 'bg-brand-100 text-brand-700', 'green' => 'bg-emerald-100 text-emerald-700', 'amber' => 'bg-amber-100 text-amber-700',
        'rose' => 'bg-rose-100 text-rose-700', 'sky' => 'bg-sky-100 text-sky-700', 'violet' => 'bg-violet-100 text-violet-700', 'slate' => 'bg-slate-100 text-slate-600'];
@endphp

<ol {{ $attributes->merge(['class' => 'relative space-y-5']) }}>
    @forelse ($items as $item)
        <li class="relative flex gap-3">
            @unless ($loop->last)<span class="absolute left-4 top-9 -bottom-5 w-px bg-slate-200" aria-hidden="true"></span>@endunless
            <span class="relative grid size-8 shrink-0 place-items-center rounded-full {{ $tones[$item['tone'] ?? 'slate'] ?? $tones['slate'] }}">
                <x-ui.icon :name="$item['icon'] ?? 'clock'" class="size-4" />
            </span>
            <div class="min-w-0 flex-1 pt-1">
                <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                    <p class="text-sm font-medium text-slate-900">{{ $item['title'] }}</p>
                    <time class="tabular text-xs text-slate-400" datetime="{{ $item['at']->toIso8601String() }}" title="{{ $item['at']->format('d M Y, H:i') }}">{{ $item['at']->format('d M, H:i') }}</time>
                </div>
                @if (! empty($item['body']))<p class="mt-0.5 whitespace-pre-line text-sm text-slate-600">{{ $item['body'] }}</p>@endif
                @if (! empty($item['actor']))<p class="mt-0.5 text-xs text-slate-400">{{ $item['actor'] }}</p>@endif
            </div>
        </li>
    @empty
        <li><x-ui.empty-state :title="__('No activity yet')" icon="clock" /></li>
    @endforelse
</ol>
