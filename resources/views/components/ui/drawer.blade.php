@props(['title', 'description' => null, 'width' => 'max-w-xl'])

{{-- Slide-over panel bound to a Livewire boolean via wire:model="property". --}}
<div x-data="{ open: $wire.entangle('{{ $attributes->wire('model')->value() }}') }" x-show="open" x-cloak
    x-on:keydown.escape.window="open = false" class="relative z-50" role="dialog" aria-modal="true" aria-label="{{ $title }}">
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/40" x-on:click="open = false"></div>

    <div class="fixed inset-y-0 right-0 flex w-full {{ $width }}">
        <div x-show="open" x-trap.noscroll="open"
            x-transition:enter="transform transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
            class="flex h-full w-full flex-col bg-white shadow-(--shadow-overlay)">
            <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                    @if ($description)<p class="mt-0.5 text-sm text-slate-500">{{ $description }}</p>@endif
                </div>
                <button type="button" x-on:click="open = false" class="focus-ring rounded-md p-1 text-slate-400 hover:text-slate-600">
                    <span class="sr-only">{{ __('Close') }}</span>
                    <x-ui.icon name="x" class="size-5" />
                </button>
            </header>
            <div class="flex-1 overflow-y-auto px-6 py-5">
                {{ $slot }}
            </div>
            @isset($footer)
                <footer class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-3">
                    {{ $footer }}
                </footer>
            @endisset
        </div>
    </div>
</div>
