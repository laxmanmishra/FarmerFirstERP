<div>
    <x-ui.page-header :title="__('Territory')" :description="__('Salesman coverage by district, tehsil or village. New enquiries go to the most specific primary salesman.')"
        :breadcrumbs="[__('CRM') => null, __('Territory') => null]">
        <x-slot:actions>
            @can('territory.assign')
                <x-ui.button icon="plus" wire:click="create">{{ __('Assign territory') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.table :paginator="$assignments">
        <x-slot:toolbar>
            <select wire:model.live="employee" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Salesman') }}">
                <option value="">{{ __('All salesmen') }}</option>
                @foreach ($salesmen as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <select wire:model.live="status" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Status') }}">
                <option value="active">{{ __('Active') }}</option>
                <option value="ended">{{ __('Ended') }}</option>
                <option value="">{{ __('All') }}</option>
            </select>
        </x-slot:toolbar>
        <x-slot:head>
            <x-ui.th>{{ __('Salesman') }}</x-ui.th>
            <x-ui.th>{{ __('Level') }}</x-ui.th>
            <x-ui.th>{{ __('Area') }}</x-ui.th>
            <x-ui.th>{{ __('Role') }}</x-ui.th>
            <x-ui.th>{{ __('Period') }}</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
        </x-slot:head>
        @forelse ($assignments as $assignment)
            <tr wire:key="ta-{{ $assignment->id }}" @class(['hover:bg-slate-50/70', 'opacity-60' => ! $assignment->is_active])>
                <x-ui.td>
                    <p class="font-medium text-slate-900">{{ $assignment->employee->name }}</p>
                    <p class="text-xs text-slate-400">{{ $assignment->employee->employee_code }}</p>
                </x-ui.td>
                <x-ui.td><x-ui.badge>{{ $assignment->level->label() }}</x-ui.badge></x-ui.td>
                <x-ui.td class="text-sm">{{ $assignment->areaLabel() }}</x-ui.td>
                <x-ui.td>
                    @if ($assignment->is_primary)<x-ui.badge tone="brand">{{ __('Primary') }}</x-ui.badge>@else<x-ui.badge>{{ __('Support') }}</x-ui.badge>@endif
                </x-ui.td>
                <x-ui.td class="whitespace-nowrap text-xs text-slate-500">
                    {{ $assignment->effective_from->format('d M Y') }} – {{ $assignment->effective_to?->format('d M Y') ?? __('present') }}
                    @if ($assignment->end_reason)<p class="text-slate-400">{{ $assignment->end_reason }}</p>@endif
                </x-ui.td>
                <x-ui.td align="right">
                    @if ($assignment->is_active)
                        @can('territory.assign')
                            <x-ui.button variant="danger-ghost" size="xs" wire:click="confirmEnd({{ $assignment->id }})">{{ __('End') }}</x-ui.button>
                        @endcan
                    @endif
                </x-ui.td>
            </tr>
        @empty
            <x-ui.empty-row :colspan="6" :title="__('No territory assignments')" icon="map" />
        @endforelse
    </x-ui.table>

    <x-ui.drawer wire:model="showForm" :title="__('Assign territory')">
        <form id="territory-form" wire:submit="save" class="space-y-5">
            <x-ui.select :label="__('Salesman')" wire:model="employee_id" name="employee_id" :options="$salesmen" :placeholder="__('Select…')" required />
            <x-ui.select :label="__('Level')" wire:model.live="level" name="level" :options="$levels" required />
            <div class="space-y-4">
                <x-ui.select :label="__('District')" wire:model.live="pickDistrictId" name="pickDistrictId" :options="$picker['districts']" :placeholder="__('Select district…')" required />
                @if ($level !== 'district')
                    <x-ui.select :label="__('Tehsil')" wire:model.live="pickTehsilId" name="pickTehsilId" :options="$picker['tehsils']" :placeholder="__('Select tehsil…')" required />
                @endif
                @if ($level === 'village')
                    <x-ui.select :label="__('Village')" wire:model.live="village_id" name="village_id" :options="$picker['villages']" :placeholder="__('Select village…')" required />
                @endif
            </div>
            <x-ui.checkbox :label="__('Primary salesman for this area')" :description="__('Only one primary per area; supporting salesmen can be added freely.')" wire:model.live="is_primary" />
            <x-ui.input type="date" :label="__('Effective from')" wire:model="effective_from" name="effective_from" required />

            @if ($conflict)
                <x-ui.alert tone="warning" :title="$conflict">
                    <div class="mt-2"><x-ui.checkbox :label="__('Replace the current primary salesman (their assignment ends the day before)')" wire:model="replaceExisting" /></div>
                </x-ui.alert>
            @endif
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="territory-form" wire:target="save">{{ __('Assign') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>

    @if ($modal === 'end')
        <x-ui.modal wire:model="modal" tone="danger" :title="__('End territory assignment')">
            <form id="end-form" wire:submit="end">
                <x-ui.textarea :label="__('Reason')" wire:model="endReason" name="endReason" rows="2" required />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="end-form" variant="danger">{{ __('End assignment') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
