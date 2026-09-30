<div>
    <x-ui.page-header :title="__('System Settings')" :description="__('Company profile, financial year and document numbering.')"
        :breadcrumbs="[__('Administration') => null, __('System Settings') => null]" />

    @php
        $tabs = [];
        if (auth()->user()->can('settings.view')) { $tabs['company'] = __('Company'); }
        if (auth()->user()->can('number_series.manage')) { $tabs['numbering'] = __('Number series'); }
        if (auth()->user()->can('settings.view')) { $tabs['lists'] = __('Lists'); $tabs['discounts'] = __('Discounts'); }
    @endphp
    <x-ui.tabs class="mb-6" :active="$tab" :tabs="$tabs" />

    @if ($tab === 'company')
        <x-ui.card class="max-w-3xl">
            <form wire:submit="saveCompany" class="space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input :label="__('Trading name')" wire:model="company.name" required :disabled="! $canManageSettings" />
                    <x-ui.input :label="__('Legal name')" wire:model="company.legal_name" :disabled="! $canManageSettings" />
                    <x-ui.input :label="__('GSTIN')" wire:model="company.gstin" maxlength="15" class="uppercase" :disabled="! $canManageSettings" />
                    <x-ui.input :label="__('PAN')" wire:model="company.pan" maxlength="10" class="uppercase" :disabled="! $canManageSettings" />
                    <x-ui.input :label="__('Phone')" wire:model="company.phone" :disabled="! $canManageSettings" />
                    <x-ui.input type="email" :label="__('Email')" wire:model="company.email" :disabled="! $canManageSettings" />
                </div>
                <x-ui.textarea :label="__('Registered address')" wire:model="company.address" :disabled="! $canManageSettings" />
                <x-ui.select :label="__('Financial year starts in')" wire:model="company.financial_year_start_month" :options="$months" :disabled="! $canManageSettings"
                    :hint="__('April for the Indian financial year. Drives FY-based numbering and reports.')" />
                @if ($canManageSettings)
                    <div class="flex justify-end"><x-ui.button type="submit" wire:target="saveCompany">{{ __('Save company settings') }}</x-ui.button></div>
                @endif
            </form>
        </x-ui.card>
    @elseif ($tab === 'lists')
        <livewire:admin.settings.lists />
    @elseif ($tab === 'discounts')
        <livewire:admin.settings.discounts />
    @else
        <x-ui.alert class="mb-4">
            {{ __('Tokens: {prefix}, {branch}, {fy} (e.g. 2026-27), {yyyy}, {seq}. Numbers are issued inside the saving transaction and are unique within their scope.') }}
        </x-ui.alert>

        <x-ui.table>
            <x-slot:head>
                <x-ui.th>{{ __('Document') }}</x-ui.th>
                <x-ui.th>{{ __('Format') }}</x-ui.th>
                <x-ui.th>{{ __('Resets') }}</x-ui.th>
                <x-ui.th>{{ __('Next number') }}</x-ui.th>
                <x-ui.th>{{ __('Status') }}</x-ui.th>
                <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
            </x-slot:head>
            @foreach ($seriesList as $item)
                <tr wire:key="series-{{ $item->id }}" class="hover:bg-slate-50/70">
                    <x-ui.td>
                        <p class="font-medium text-slate-900">{{ $item->name }}</p>
                        <p class="text-xs text-slate-400">{{ $item->entity }}</p>
                    </x-ui.td>
                    <x-ui.td class="font-mono text-xs text-slate-600">{{ $item->format }}</x-ui.td>
                    <x-ui.td class="text-xs">
                        {{ $item->reset_policy === App\Models\NumberSeries::RESET_FINANCIAL_YEAR ? __('Every financial year') : __('Never') }}
                        @if ($item->per_branch)<x-ui.badge class="ml-1">{{ __('Per branch') }}</x-ui.badge>@endif
                    </x-ui.td>
                    <x-ui.td class="tabular font-mono text-sm text-brand-800">{{ $previews[$item->id] ?? '—' }}</x-ui.td>
                    <x-ui.td><x-ui.active-badge :active="$item->is_active" /></x-ui.td>
                    <x-ui.td align="right"><x-ui.button variant="ghost" size="xs" icon="pencil" wire:click="editSeries({{ $item->id }})">{{ __('Edit') }}</x-ui.button></x-ui.td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.drawer wire:model="showSeriesForm" :title="__('Edit number series')" :description="__('Changes apply to numbers issued from now on; existing numbers never change.')">
            <form id="series-form" wire:submit="saveSeries" class="space-y-5">
                <x-ui.input :label="__('Name')" wire:model="series.name" required />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input :label="__('Prefix')" wire:model="series.prefix" required />
                    <x-ui.input type="number" :label="__('Sequence digits')" wire:model="series.padding" min="1" max="12" required />
                </div>
                <x-ui.input :label="__('Format')" wire:model="series.format" required class="font-mono" />
                <x-ui.select :label="__('Reset counter')" wire:model="series.reset_policy"
                    :options="[App\Models\NumberSeries::RESET_FINANCIAL_YEAR => __('Every financial year'), App\Models\NumberSeries::RESET_NEVER => __('Never')]" />
                <x-ui.checkbox :label="__('Separate sequence per branch')" wire:model="series.per_branch" />
                <x-ui.checkbox :label="__('Active')" :description="__('Inactive series cannot issue numbers; documents that need one will be refused.')" wire:model="series.is_active" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="series-form" wire:target="saveSeries">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.drawer>
    @endif
</div>
