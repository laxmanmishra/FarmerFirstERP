@php
    $labels = ['states' => __('State'), 'districts' => __('District'), 'tehsils' => __('Tehsil'), 'villages' => __('Village')];
    $parentLabels = ['districts' => __('State'), 'tehsils' => __('District'), 'villages' => __('Tehsil')];
@endphp
<div>
    <x-ui.page-header :title="__('Geography')" :description="__('Territory hierarchy used by farmers, enquiries, salesman territory and reports.')"
        :breadcrumbs="[__('Administration') => null, __('Geography') => null]">
        <x-slot:actions>
            @can('geography.manage')
                <x-ui.button icon="plus" wire:click="create">{{ __('New :level', ['level' => mb_strtolower($labels[$tab])]) }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.tabs class="mb-4" :active="$tab" :tabs="['states' => __('States'), 'districts' => __('Districts'), 'tehsils' => __('Tehsils'), 'villages' => __('Villages')]" />

    <x-ui.table :paginator="$records">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Search name or code…')" />
            <div class="flex flex-wrap gap-2">
                @if ($tab !== 'states')
                    <select wire:model.live="stateFilter" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('State') }}">
                        <option value="">{{ __('All states') }}</option>
                        @foreach ($states as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </select>
                @endif
                @if (in_array($tab, ['tehsils', 'villages'], true))
                    <select wire:model.live="districtFilter" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('District') }}">
                        <option value="">{{ __('All districts') }}</option>
                        @foreach ($districts as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </select>
                @endif
                @if ($tab === 'villages')
                    <select wire:model.live="tehsilFilter" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Tehsil') }}">
                        <option value="">{{ __('All tehsils') }}</option>
                        @foreach ($tehsils as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </select>
                @endif
            </div>
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th sortable="name" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ $labels[$tab] }}</x-ui.th>
            <x-ui.th sortable="code" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Code') }}</x-ui.th>
            @if ($tab !== 'states')<x-ui.th>{{ __('Belongs to') }}</x-ui.th>@endif
            @if ($tab === 'villages')<x-ui.th>{{ __('PIN') }}</x-ui.th>@else<x-ui.th align="right">{{ __('Children') }}</x-ui.th>@endif
            <x-ui.th>{{ __('Status') }}</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
        </x-slot:head>

        @forelse ($records as $record)
            <tr wire:key="{{ $tab }}-{{ $record->id }}" class="hover:bg-slate-50/70">
                <x-ui.td class="font-medium text-slate-900">{{ $record->name }}</x-ui.td>
                <x-ui.td class="text-xs text-slate-500">{{ $record->code ?? '—' }}</x-ui.td>
                @if ($tab === 'districts')<x-ui.td>{{ $record->state->name }}</x-ui.td>@endif
                @if ($tab === 'tehsils')<x-ui.td>{{ $record->district->name }}</x-ui.td>@endif
                @if ($tab === 'villages')<x-ui.td>{{ $record->tehsil->name }} <span class="text-slate-400">· {{ $record->tehsil->district->name }}</span></x-ui.td>@endif
                @if ($tab === 'villages')
                    <x-ui.td class="tabular text-slate-500">{{ $record->pin_code ?? '—' }}</x-ui.td>
                @else
                    <x-ui.td align="right" class="tabular">{{ $record->districts_count ?? $record->tehsils_count ?? $record->villages_count ?? 0 }}</x-ui.td>
                @endif
                <x-ui.td><x-ui.active-badge :active="$record->is_active" /></x-ui.td>
                <x-ui.td align="right">
                    @can('geography.manage')
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
            <x-ui.empty-row :colspan="6" :title="__('No records match these filters')" icon="map">
                @can('geography.import'){{ __('Bulk Excel/CSV import arrives with the CRM phase.') }}@endcan
            </x-ui.empty-row>
        @endforelse
    </x-ui.table>

    <x-ui.drawer wire:model="showForm" :title="($editingId ? __('Edit') : __('New')).' '.mb_strtolower($labels[$tab])">
        <form id="geo-form" wire:submit="save" class="space-y-5">
            @if ($tab !== 'states')
                <x-ui.select :label="$parentLabels[$tab]" wire:model="parent_id" name="parent_id" :options="$parentOptions" :placeholder="__('Select…')" required />
            @endif
            <x-ui.input :label="__('Name')" wire:model="name" required />
            <x-ui.input :label="__('Code')" wire:model="code" :required="$tab === 'states'" :hint="__('Optional government (LGD) or internal code; must be unique.')" />
            @if ($tab === 'villages')
                <x-ui.input :label="__('PIN code')" wire:model="pin_code" inputmode="numeric" maxlength="6" />
            @endif
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="geo-form" wire:target="save">{{ __('Save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>
</div>
