@props(['title', 'description' => null, 'width' => 'max-w-lg', 'tone' => null])

{{-- Centered dialog bound to a Livewire boolean via wire:model="property". --}}
<div x-data="{ open: $wire.entangle('{{ $attributes->wire('model')->value() }}') }" x-show="open" x-cloak
    x-on:keydown.escape.window="open = false" class="relative z-50" role="dialog" aria-modal="true" aria-label="{{ $title }}">
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/40"></div>

    <div class="fixed inset-0 flex items-end justify-center p-4 sm:items-center">
        <div x-show="open" x-trap.noscroll="open" x-on:click.outside="open = false"
            x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            class="w-full {{ $width }} overflow-hidden rounded-xl bg-white shadow-(--shadow-overlay)">
            <div class="flex gap-4 px-6 pt-5">
                @if ($tone === 'danger')
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-rose-100 text-rose-600"><x-ui.icon name="exclamation" class="size-5" /></span>
                @endif
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                    @if ($description)<p class="mt-1 text-sm text-slate-500">{{ $description }}</p>@endif
                    <div class="mt-4">{{ $slot }}</div>
                </div>
            </div>
            @isset($footer)
                <footer class="mt-5 flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50 px-6 py-3">{{ $footer }}</footer>
            @else
                <div class="pb-5"></div>
            @endisset
        </div>
    </div>
</div>
