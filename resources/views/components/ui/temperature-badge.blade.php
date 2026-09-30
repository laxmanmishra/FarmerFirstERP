@props(['temperature'])

@if ($temperature)
    <x-ui.badge :tone="$temperature->tone()" {{ $attributes }}>
        <svg class="size-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2c.5 3-1.5 4.5-3 6.5S6.5 12.5 7 15a5 5 0 0 0 10 0c0-2-1-3.5-2-4.5.2 1.6-.4 2.8-1.5 3.3.4-2.8-.5-6.3-1.5-11.8Z"/></svg>
        {{ $temperature->label() }}
    </x-ui.badge>
@endif
