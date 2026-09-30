<div class="max-w-3xl">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <select wire:model.live="type" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('List') }}">
            @foreach ($types as $key => $label)<option value="{{ $key }}">{{ __($label) }}</option>@endforeach
        </select>
        @if ($canManage)
            <x-ui.button size="sm" icon="plus" wire:click="create">{{ __('Add value') }}</x-ui.button>
        @endif
    </div>

    <x-ui.table>
        <x-slot:head>
            <x-ui.th>{{ __('Name') }}</x-ui.th>
            <x-ui.th>{{ __('Code') }}</x-ui.th>
            <x-ui.th>{{ __('Status') }}</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
        </x-slot:head>
        @foreach ($values as $value)
            <tr wire:key="lv-{{ $value->id }}" @class(['opacity-60' => ! $value->is_active])>
                <x-ui.td class="font-medium text-slate-900">{{ $value->name }}</x-ui.td>
                <x-ui.td class="font-mono text-xs text-slate-500">{{ $value->code }}</x-ui.td>
                <x-ui.td><x-ui.active-badge :active="$value->is_active" /></x-ui.td>
                <x-ui.td align="right">
                    @if ($canManage)
                        <div class="flex justify-end gap-1">
                            <x-ui.button variant="ghost" size="xs" icon="pencil" wire:click="edit({{ $value->id }})">{{ __('Rename') }}</x-ui.button>
                            <x-ui.button variant="ghost" size="xs" wire:click="toggle({{ $value->id }})">{{ $value->is_active ? __('Deactivate') : __('Activate') }}</x-ui.button>
                        </div>
                    @endif
                </x-ui.td>
            </tr>
        @endforeach
    </x-ui.table>

    <x-ui.drawer wire:model="showForm" :title="$editingId ? __('Rename value') : __('Add value')">
        <form id="lookup-form" wire:submit="save" class="space-y-4">
            <x-ui.input :label="__('Code')" wire:model="code" name="code" :disabled="(bool) $editingId" class="font-mono uppercase" :hint="__('Stored on records; cannot change later.')" />
            <x-ui.input :label="__('Name')" wire:model="name" name="name" required />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="lookup-form">{{ __('Save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>
</div>
