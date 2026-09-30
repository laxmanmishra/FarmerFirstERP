<div>
    <x-ui.page-header :title="__('Customers')" :description="__('Created automatically when an enquiry is won. One farmer is at most one customer.')"
        :breadcrumbs="[__('Sales') => null, __('Customers') => null]" />

    <x-ui.table :paginator="$customers">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Name, customer no or mobile…')" />
            @if ($duplicateCount > 0)
                <x-ui.checkbox :label="__('Possible duplicates (:count)', ['count' => $duplicateCount])" wire:model.live="duplicatesOnly" />
            @endif
        </x-slot:toolbar>
        <x-slot:head>
            <x-ui.th sortable="customer_no" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Customer no') }}</x-ui.th>
            <x-ui.th sortable="name" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Customer') }}</x-ui.th>
            <x-ui.th>{{ __('Location') }}</x-ui.th>
            <x-ui.th align="right">{{ __('Enquiries') }}</x-ui.th>
            <x-ui.th align="right">{{ __('Deals') }}</x-ui.th>
            <x-ui.th sortable="customer_since" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Since') }}</x-ui.th>
        </x-slot:head>
        @forelse ($customers as $customer)
            <tr wire:key="c-{{ $customer->id }}" class="hover:bg-slate-50/70">
                <x-ui.td class="tabular text-xs font-medium text-slate-500">{{ $customer->customer_no }}</x-ui.td>
                <x-ui.td>
                    <a href="{{ route('sales.customers.show', $customer) }}" wire:navigate class="font-medium text-slate-900 hover:text-brand-700">{{ $customer->name }}</a>
                    <p class="tabular text-xs text-slate-500">{{ $customer->mobile }}</p>
                    @if ($customer->possibleDuplicateOf)
                        <x-ui.badge tone="amber" class="mt-1">{{ __('Same mobile as :no', ['no' => $customer->possibleDuplicateOf->customer_no]) }}</x-ui.badge>
                    @endif
                </x-ui.td>
                <x-ui.td class="text-sm">{{ $customer->locationLabel() }}</x-ui.td>
                <x-ui.td align="right" class="tabular">{{ $customer->enquiries_count }}</x-ui.td>
                <x-ui.td align="right" class="tabular">{{ $customer->deals_count }}</x-ui.td>
                <x-ui.td class="whitespace-nowrap text-sm">{{ $customer->customer_since->format('d M Y') }}</x-ui.td>
            </tr>
        @empty
            <x-ui.empty-row :colspan="6" :title="__('No customers yet')" icon="identification">{{ __('Customers appear when an enquiry is marked Won.') }}</x-ui.empty-row>
        @endforelse
    </x-ui.table>
</div>
