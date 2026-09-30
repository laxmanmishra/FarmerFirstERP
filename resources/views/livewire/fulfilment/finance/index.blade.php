@php use App\Support\Money; @endphp
<div>
    <x-ui.page-header :title="__('Retail & Finance')" :description="__('Finance files are opened automatically for every order that needs finance. Status follows what the financer reports.')"
        :breadcrumbs="[__('Fulfilment') => null, __('Retail & Finance') => null]" />

    @can('finance.configure')
        <x-ui.tabs class="mb-6" :active="$tab" :tabs="['files' => __('Finance files'), 'financers' => __('Financers')]" />
    @endcan

    @if ($tab === 'financers')
        <livewire:fulfilment.finance.financers />
    @else
        <x-ui.table :paginator="$files">
            <x-slot:toolbar>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('File, order, customer or mobile…')" />
                    <select wire:model.live="stage" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Status') }}">
                        <option value="">{{ __('Any status') }}</option>
                        @foreach ($stages as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
                    </select>
                    <select wire:model.live="financer" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Financer') }}">
                        <option value="">{{ __('Any financer') }}</option>
                        @foreach ($financers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    <x-ui.checkbox :label="__('Assigned to me')" wire:model.live="mine" />
                    <x-ui.checkbox :label="__('Open only')" wire:model.live="open" />
                </div>
            </x-slot:toolbar>
            <x-slot:head>
                <x-ui.th sortable="file_no" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('File') }}</x-ui.th>
                <x-ui.th>{{ __('Customer') }}</x-ui.th>
                <x-ui.th sortable="loan_amount" :sort-by="$sortBy" :sort-direction="$sortDirection" align="right">{{ __('Loan') }}</x-ui.th>
                <x-ui.th>{{ __('Financer') }}</x-ui.th>
                <x-ui.th>{{ __('Responsible') }}</x-ui.th>
                <x-ui.th>{{ __('Queries') }}</x-ui.th>
                <x-ui.th sortable="updated_at" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Updated') }}</x-ui.th>
                <x-ui.th>{{ __('Status') }}</x-ui.th>
            </x-slot:head>
            @forelse ($files as $file)
                <tr wire:key="ff-{{ $file->id }}" class="hover:bg-slate-50/70">
                    <x-ui.td>
                        <a href="{{ route('fulfilment.finance.show', $file) }}" wire:navigate class="tabular font-semibold text-slate-900 hover:text-brand-700">{{ $file->file_no }}</a>
                        <p class="tabular text-xs text-slate-500">{{ $file->order->order_no }}</p>
                    </x-ui.td>
                    <x-ui.td><p class="font-medium text-slate-800">{{ $file->order->customer->name }}</p><p class="text-xs text-slate-500">{{ $file->order->customer->mobile }}</p></x-ui.td>
                    <x-ui.td align="right" class="tabular font-medium">{{ Money::format($file->loan_amount) }}</x-ui.td>
                    <x-ui.td class="text-sm">{{ $file->financer?->name ?? '—' }}</x-ui.td>
                    <x-ui.td class="text-sm">{{ $file->responsible?->name ?? __('Unassigned') }}</x-ui.td>
                    <x-ui.td>@if ($file->open_queries > 0)<x-ui.badge tone="rose">{{ trans_choice(':count open|:count open', $file->open_queries, ['count' => $file->open_queries]) }}</x-ui.badge>@else<span class="text-slate-400">—</span>@endif</x-ui.td>
                    <x-ui.td class="whitespace-nowrap text-xs text-slate-500">{{ $file->updated_at->diffForHumans() }}</x-ui.td>
                    <x-ui.td class="whitespace-nowrap"><x-ui.stage-badge :stage="$file->stage" /></x-ui.td>
                </tr>
            @empty
                <x-ui.empty-row :colspan="8" :title="__('No finance files')" icon="banknotes" />
            @endforelse
        </x-ui.table>
    @endif
</div>
