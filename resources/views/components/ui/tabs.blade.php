@props(['tabs' => [], 'active' => null, 'model' => 'tab'])

{{-- Server-driven tabs: $tabs = ['key' => 'Label'], selection stored in a Livewire property. --}}
<div {{ $attributes->merge(['class' => 'border-b border-slate-200']) }}>
    <nav class="-mb-px flex gap-6 overflow-x-auto" aria-label="Tabs">
        @foreach ($tabs as $key => $label)
            <button type="button" wire:click="$set('{{ $model }}', '{{ $key }}')" @class([
                'whitespace-nowrap border-b-2 px-1 pb-3 pt-1 text-sm font-medium transition-colors focus-ring',
                'border-brand-600 text-brand-700' => $active === $key,
                'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' => $active !== $key,
            ]) @if ($active === $key) aria-current="page" @endif>
                {{ $label }}
            </button>
        @endforeach
    </nav>
</div>
