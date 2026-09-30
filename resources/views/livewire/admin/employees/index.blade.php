<div>
    <x-ui.page-header :title="__('Employees')" :description="__('Organisation records: departments, branches, designation and reporting line.')"
        :breadcrumbs="[__('Administration') => null, __('Employees') => null]">
        <x-slot:actions>
            @can('employees.create')
                <x-ui.button icon="plus" wire:click="create">{{ __('New employee') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.table :paginator="$employees">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Search name, code or mobile…')" />
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="department" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Department') }}">
                    <option value="">{{ __('All departments') }}</option>
                    @foreach ($departments as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
                </select>
                @if ($branches->count() > 1)
                    <select wire:model.live="branch" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Branch') }}">
                        <option value="">{{ __('All branches') }}</option>
                        @foreach ($branches as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
                    </select>
                @endif
                <select wire:model.live="status" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Status') }}">
                    <option value="active">{{ __('Active') }}</option>
                    <option value="inactive">{{ __('Inactive') }}</option>
                    <option value="">{{ __('All') }}</option>
                </select>
            </div>
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th sortable="employee_code" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Code') }}</x-ui.th>
            <x-ui.th sortable="name" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Employee') }}</x-ui.th>
            <x-ui.th>{{ __('Departments') }}</x-ui.th>
            <x-ui.th>{{ __('Reports to') }}</x-ui.th>
            <x-ui.th>{{ __('Login') }}</x-ui.th>
            <x-ui.th>{{ __('Status') }}</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
        </x-slot:head>

        @forelse ($employees as $employee)
            <tr wire:key="employee-{{ $employee->id }}" class="hover:bg-slate-50/70">
                <x-ui.td class="tabular text-xs font-medium text-slate-500">{{ $employee->employee_code }}</x-ui.td>
                <x-ui.td>
                    <p class="font-medium text-slate-900">{{ $employee->name }}</p>
                    <p class="text-xs text-slate-500">{{ $employee->designation?->name ?? '—' }}@if ($employee->mobile) · {{ $employee->mobile }}@endif</p>
                </x-ui.td>
                <x-ui.td>
                    <div class="flex max-w-xs flex-wrap gap-1">
                        @foreach ($employee->departments as $membership)
                            <x-ui.badge :tone="$membership->pivot->is_primary ? 'brand' : 'slate'">{{ $membership->name }}</x-ui.badge>
                        @endforeach
                    </div>
                    @if ($branches->count() > 1)
                        <p class="mt-1 text-xs text-slate-500">{{ $employee->branches->pluck('name')->implode(', ') }}</p>
                    @endif
                </x-ui.td>
                <x-ui.td class="text-sm">{{ $employee->manager?->name ?? '—' }}</x-ui.td>
                <x-ui.td class="text-xs">
                    @if ($employee->user)
                        <span @class(['text-slate-600', 'line-through text-slate-400' => ! $employee->user->is_active])>{{ $employee->user->email }}</span>
                    @else
                        <span class="text-slate-400">{{ __('No login') }}</span>
                    @endif
                </x-ui.td>
                <x-ui.td><x-ui.active-badge :active="$employee->is_active" /></x-ui.td>
                <x-ui.td align="right">
                    <div class="flex justify-end gap-1 whitespace-nowrap">
                        @can('employees.update')
                            <x-ui.button variant="ghost" size="xs" icon="pencil" wire:click="edit({{ $employee->id }})">{{ __('Edit') }}</x-ui.button>
                        @endcan
                        @can('employees.deactivate')
                            <x-ui.button :variant="$employee->is_active ? 'danger-ghost' : 'ghost'" size="xs" wire:click="toggleActive({{ $employee->id }})"
                                wire:confirm="{{ $employee->is_active ? __('Deactivate :name?', ['name' => $employee->name]) : __('Reactivate :name?', ['name' => $employee->name]) }}">
                                {{ $employee->is_active ? __('Deactivate') : __('Activate') }}
                            </x-ui.button>
                        @endcan
                    </div>
                </x-ui.td>
            </tr>
        @empty
            <x-ui.empty-row :colspan="7" :title="__('No employees match these filters')" icon="users" />
        @endforelse
    </x-ui.table>

    <x-ui.drawer wire:model="showForm" width="max-w-2xl" :title="$editingId ? __('Edit employee') : __('New employee')"
        :description="$editingId ? null : __('The employee code is issued automatically from the number series.')">
        <form id="employee-form" wire:submit="save" class="space-y-6">
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2"><x-ui.input :label="__('Full name')" wire:model="name" required /></div>
                <x-ui.input :label="__('Mobile')" wire:model="mobile" inputmode="numeric" maxlength="10" />
                <x-ui.input type="email" :label="__('Email')" wire:model="email" />
                <x-ui.select :label="__('Designation')" wire:model="designation_id" :options="$designations" :placeholder="__('Select…')" />
                <x-ui.input type="date" :label="__('Date of joining')" wire:model="date_of_joining" />
                <x-ui.select :label="__('Reports to')" wire:model="reports_to_id" :options="$managers" :placeholder="__('No manager')"
                    :hint="__('Defines team visibility for managers.')" />
                <x-ui.select :label="__('Login account')" wire:model="user_id" :options="$linkableUsers" :placeholder="__('No login')"
                    :hint="__('Create accounts under Users first.')" />
            </div>

            <fieldset>
                <legend class="text-sm font-medium text-slate-700">{{ __('Departments') }} <span class="text-rose-500">*</span></legend>
                <p class="text-xs text-slate-500">{{ __('An employee may work in several departments. Mark one as primary.') }}</p>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($departments as $option)
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2">
                            <x-ui.checkbox :label="$option->name" wire:model.live="department_ids" value="{{ $option->id }}" />
                            @if (in_array($option->id, array_map('intval', $department_ids), true))
                                <label class="flex items-center gap-1 text-xs text-slate-500">
                                    <input type="radio" wire:model="primary_department_id" value="{{ $option->id }}" class="accent-brand-700"> {{ __('Primary') }}
                                </label>
                            @endif
                        </div>
                    @endforeach
                </div>
                @error('department_ids')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </fieldset>

            <fieldset>
                <legend class="text-sm font-medium text-slate-700">{{ __('Branches') }} <span class="text-rose-500">*</span></legend>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($branches as $option)
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2">
                            <x-ui.checkbox :label="$option->name" wire:model.live="branch_ids" value="{{ $option->id }}" />
                            @if (in_array($option->id, array_map('intval', $branch_ids), true))
                                <label class="flex items-center gap-1 text-xs text-slate-500">
                                    <input type="radio" wire:model="primary_branch_id" value="{{ $option->id }}" class="accent-brand-700"> {{ __('Primary') }}
                                </label>
                            @endif
                        </div>
                    @endforeach
                </div>
                @error('branch_ids')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </fieldset>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="employee-form" wire:target="save">{{ $editingId ? __('Save changes') : __('Create employee') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>
</div>
