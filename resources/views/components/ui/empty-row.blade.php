@props(['colspan' => 1, 'title' => null, 'icon' => 'inbox'])

<tr>
    <td colspan="{{ $colspan }}" class="px-4 py-12">
        <x-ui.empty-state :title="$title ?? __('No records found')" :icon="$icon">{{ $slot }}</x-ui.empty-state>
    </td>
</tr>
