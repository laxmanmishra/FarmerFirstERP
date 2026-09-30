<div>
    <x-ui.page-header :title="__('Branches')" :description="__('Operating locations. Stock, orders, numbering and reports can be branch-scoped.')"
        :breadcrumbs="[__('Administration') => null, __('Branches') => null]">
        <x-slot:actions>
            @can('branches.manage')
                <x-ui.button icon="plus" wire:click="create">{{ __('New branch') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.table :paginator="$branches">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Search code or name…')" />
        </x-slot:toolbar>
        <x-slot:head>
            <x-ui.th sortable="code" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Code') }}</x-ui.th>
            <x-ui.th sortable="name" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Branch') }}</x-ui.th>
            <x-ui.th>{{ __('District') }}</x-ui.th>
            <x-ui.th>{{ __('Contact') }}</x-ui.th>
            <x-ui.th align="right">{{ __('Employees') }}</x-ui.th>
            <x-ui.th>{{ __('Status') }}</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
        </x-slot:head>

        @forelse ($branches as $branch)
            <tr wire:key="branch-{{ $branch->id }}" class="hover:bg-slate-50/70">
                <x-ui.td class="text-xs font-semibold text-slate-600">{{ $branch->code }}</x-ui.td>
                <x-ui.td>
                    <p class="font-medium text-slate-900">{{ $branch->name }}</p>
                    @if ($branch->address)<p class="max-w-sm truncate text-xs text-slate-500">{{ $branch->address }}</p>@endif
                </x-ui.td>
                <x-ui.td>{{ $branch->district?->name ?? '—' }}</x-ui.td>
                <x-ui.td class="text-xs text-slate-600">{{ collect([$branch->phone, $branch->email])->filter()->implode(' · ') ?: '—' }}</x-ui.td>
                <x-ui.td align="right" class="tabular">{{ $branch->employees_count }}</x-ui.td>
                <x-ui.td><x-ui.active-badge :active="$branch->is_active" /></x-ui.td>
                <x-ui.td align="right">
                    @can('branches.manage')
                        <div class="flex justify-end gap-1 whitespace-nowrap">
                            <x-ui.button variant="ghost" size="xs" icon="pencil" wire:click="edit({{ $branch->id }})">{{ __('Edit') }}</x-ui.button>
                            <x-ui.button :variant="$branch->is_active ? 'danger-ghost' : 'ghost'" size="xs" wire:click="toggleActive({{ $branch->id }})"
                                wire:confirm="{{ $branch->is_active ? __('Deactivate :name?', ['name' => $branch->name]) : __('Activate :name?', ['name' => $branch->name]) }}">
                                {{ $branch->is_active ? __('Deactivate') : __('Activate') }}
                            </x-ui.button>
                        </div>
                    @endcan
                </x-ui.td>
            </tr>
        @empty
            <x-ui.empty-row :colspan="7" :title="__('No branches found')" icon="building" />
        @endforelse
    </x-ui.table>

    <x-ui.drawer wire:model="showForm" :title="$editingId ? __('Edit branch') : __('New branch')">
        <form id="branch-form" wire:submit="save" class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-ui.input :label="__('Code')" wire:model="code" required maxlength="20" :hint="__('Used in document numbers.')" />
                <div class="sm:col-span-2"><x-ui.input :label="__('Name')" wire:model="name" required /></div>
            </div>
            <x-ui.textarea :label="__('Address')" wire:model="address" />
            <x-ui.select :label="__('District')" wire:model="district_id" :options="$districts" :placeholder="__('Select…')" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input :label="__('Phone')" wire:model="phone" />
                <x-ui.input type="email" :label="__('Email')" wire:model="email" />
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="branch-form" wire:target="save">{{ __('Save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>
</div>
