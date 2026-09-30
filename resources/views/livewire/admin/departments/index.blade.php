<div>
    <x-ui.page-header :title="__('Departments & Designations')" :description="__('Operational departments own fulfilment queues, documents and waivers.')"
        :breadcrumbs="[__('Administration') => null, __('Departments') => null]">
        <x-slot:actions>
            @can('departments.manage')
                <x-ui.button icon="plus" wire:click="create">{{ $tab === 'departments' ? __('New department') : __('New designation') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.tabs class="mb-4" :active="$tab" :tabs="['departments' => __('Departments'), 'designations' => __('Designations')]" />

    <x-ui.table>
        <x-slot:head>
            <x-ui.th>{{ __('Code') }}</x-ui.th>
            <x-ui.th>{{ __('Name') }}</x-ui.th>
            @if ($tab === 'departments')
                <x-ui.th>{{ __('Type') }}</x-ui.th>
                <x-ui.th align="right">{{ __('Order') }}</x-ui.th>
            @endif
            <x-ui.th align="right">{{ __('Employees') }}</x-ui.th>
            <x-ui.th>{{ __('Status') }}</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
        </x-slot:head>

        @forelse ($tab === 'departments' ? $departments : $designations as $record)
            <tr wire:key="{{ $tab }}-{{ $record->id }}" class="hover:bg-slate-50/70">
                <x-ui.td class="text-xs font-semibold text-slate-600">{{ $record->code }}</x-ui.td>
                <x-ui.td>
                    <p class="font-medium text-slate-900">{{ $record->name }}</p>
                    @if ($tab === 'departments' && $record->description)<p class="text-xs text-slate-500">{{ $record->description }}</p>@endif
                </x-ui.td>
                @if ($tab === 'departments')
                    <x-ui.td>
                        @if ($record->is_operational)<x-ui.badge tone="sky">{{ __('Fulfilment') }}</x-ui.badge>@else<x-ui.badge>{{ __('Support') }}</x-ui.badge>@endif
                    </x-ui.td>
                    <x-ui.td align="right" class="tabular text-slate-500">{{ $record->sort_order }}</x-ui.td>
                @endif
                <x-ui.td align="right" class="tabular">{{ $record->employees_count }}</x-ui.td>
                <x-ui.td><x-ui.active-badge :active="$record->is_active" /></x-ui.td>
                <x-ui.td align="right">
                    @can('departments.manage')
                        <div class="flex justify-end gap-1 whitespace-nowrap">
                            <x-ui.button variant="ghost" size="xs" icon="pencil" wire:click="edit({{ $record->id }})">{{ __('Edit') }}</x-ui.button>
                            <x-ui.button :variant="$record->is_active ? 'danger-ghost' : 'ghost'" size="xs" wire:click="toggleActive({{ $record->id }})">
                                {{ $record->is_active ? __('Deactivate') : __('Activate') }}
                            </x-ui.button>
                        </div>
                    @endcan
                </x-ui.td>
            </tr>
        @empty
            <x-ui.empty-row :colspan="7" :title="__('Nothing here yet')" icon="squares" />
        @endforelse
    </x-ui.table>

    <x-ui.drawer wire:model="showForm" :title="($editingId ? __('Edit') : __('New')).' '.($tab === 'departments' ? __('department') : __('designation'))">
        <form id="department-form" wire:submit="save" class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-ui.input :label="__('Code')" wire:model="code" required maxlength="30" />
                <div class="sm:col-span-2"><x-ui.input :label="__('Name')" wire:model="name" required /></div>
            </div>
            @if ($tab === 'departments')
                <x-ui.input :label="__('Description')" wire:model="description" />
                <x-ui.input type="number" :label="__('Display order')" wire:model="sort_order" min="0" />
                <x-ui.checkbox :label="__('Fulfilment department')" :description="__('Owns order fulfilment tasks, queues and waivers (e.g. Accounts, RTO, PDI).')" wire:model="is_operational" />
            @endif
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="department-form" wire:target="save">{{ __('Save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>
</div>
