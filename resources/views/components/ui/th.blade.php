@props(['sortable' => null, 'sortBy' => null, 'sortDirection' => 'asc', 'align' => 'left'])

<th scope="col" {{ $attributes->merge(['class' => 'whitespace-nowrap px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-'.$align]) }}>
    @if ($sortable)
        <button type="button" wire:click="sort('{{ $sortable }}')" class="group inline-flex items-center gap-1 uppercase hover:text-slate-800">
            {{ $slot }}
            @if ($sortBy === $sortable)
                <x-ui.icon :name="$sortDirection === 'asc' ? 'arrow-up' : 'arrow-down'" class="size-3 text-brand-600" />
            @else
                <x-ui.icon name="chevron-up-down" class="size-3 text-slate-300 group-hover:text-slate-400" />
            @endif
        </button>
    @else
        {{ $slot }}
    @endif
</th>
