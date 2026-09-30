@props(['title', 'icon' => 'inbox'])

<div {{ $attributes->merge(['class' => 'mx-auto flex max-w-sm flex-col items-center text-center']) }}>
    <span class="grid size-12 place-items-center rounded-full bg-slate-100 text-slate-400">
        <x-ui.icon :name="$icon" class="size-6" />
    </span>
    <h3 class="mt-3 text-sm font-semibold text-slate-900">{{ $title }}</h3>
    @if ($slot->isNotEmpty())
        <div class="mt-1 text-sm text-slate-500">{{ $slot }}</div>
    @endif
    @isset($action)<div class="mt-4">{{ $action }}</div>@endisset
</div>
