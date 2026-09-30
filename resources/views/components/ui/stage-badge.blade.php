@props(['stage'])

@if ($stage)
    <x-ui.badge :tone="$stage->color" dot {{ $attributes }}>{{ $stage->name }}</x-ui.badge>
@else
    <span class="text-slate-400">—</span>
@endif
