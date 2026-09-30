@php use App\Support\Money; @endphp
<div>
    <x-ui.page-header :title="$approvals ? __('Deal Approvals') : __('Deals')"
        :description="$approvals ? __('Deals marked ready, oldest first. You cannot decide deals you submitted or own.') : __('Deals are created automatically when an enquiry is won.')"
        :breadcrumbs="[__('Sales') => null, ($approvals ? __('Deal Approvals') : __('Deals')) => null]" />

    <x-ui.table :paginator="$deals">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Deal no, customer or mobile…')" />
            @unless ($approvals)
                <select wire:model.live="stage" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Status') }}">
                    <option value="">{{ __('Any status') }}</option>
                    @foreach ($stages as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
                </select>
            @endunless
        </x-slot:toolbar>
        <x-slot:head>
            <x-ui.th sortable="deal_no" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Deal') }}</x-ui.th>
            <x-ui.th>{{ __('Customer') }}</x-ui.th>
            <x-ui.th sortable="deal_value" :sort-by="$sortBy" :sort-direction="$sortDirection" align="right">{{ __('Value') }}</x-ui.th>
            <x-ui.th>{{ __('Finance') }}</x-ui.th>
            <x-ui.th sortable="expected_delivery_date" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Expected delivery') }}</x-ui.th>
            <x-ui.th>{{ __('Salesman') }}</x-ui.th>
            <x-ui.th>{{ $approvals ? __('Submitted') : __('Status') }}</x-ui.th>
        </x-slot:head>
        @forelse ($deals as $deal)
            <tr wire:key="deal-{{ $deal->id }}" class="hover:bg-slate-50/70">
                <x-ui.td><a href="{{ route('sales.deals.show', $deal) }}" wire:navigate class="tabular font-semibold text-slate-900 hover:text-brand-700">{{ $deal->deal_no }}</a></x-ui.td>
                <x-ui.td>
                    <p class="font-medium text-slate-800">{{ $deal->customer->name }}</p>
                    <p class="text-xs text-slate-500">{{ $deal->customer->customer_no }} · {{ $deal->customer->mobile }}</p>
                </x-ui.td>
                <x-ui.td align="right" class="tabular font-medium">{{ Money::format($deal->deal_value) }}</x-ui.td>
                <x-ui.td class="text-sm">{{ $deal->finance_required ? Money::format($deal->finance_amount) : __('Cash') }}</x-ui.td>
                <x-ui.td class="whitespace-nowrap text-sm">{{ $deal->expected_delivery_date?->format('d M Y') ?? '—' }}</x-ui.td>
                <x-ui.td class="text-sm">{{ $deal->primarySalesman?->name ?? '—' }}</x-ui.td>
                <x-ui.td class="whitespace-nowrap">
                    @if ($approvals)
                        <span class="text-sm">{{ $deal->submitted_at?->diffForHumans() }}</span>
                    @else
                        <x-ui.stage-badge :stage="$deal->stage" />
                    @endif
                </x-ui.td>
            </tr>
        @empty
            <x-ui.empty-row :colspan="7" :title="$approvals ? __('Nothing waiting for approval') : __('No deals yet')" icon="check-badge" />
        @endforelse
    </x-ui.table>
</div>
