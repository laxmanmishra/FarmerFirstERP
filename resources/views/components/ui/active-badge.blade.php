@props(['active'])

<x-ui.badge :tone="$active ? 'green' : 'slate'" dot>{{ $active ? __('Active') : __('Inactive') }}</x-ui.badge>
