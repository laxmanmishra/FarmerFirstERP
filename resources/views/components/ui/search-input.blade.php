@props(['placeholder' => __('Search…')])

<div class="relative w-full sm:max-w-xs">
    <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
    <input type="search" placeholder="{{ $placeholder }}" {{ $attributes->merge(['class' => 'form-control pl-9']) }} />
</div>
