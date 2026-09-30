@php use App\Support\Money; @endphp
<div>
    <x-ui.page-header :title="__('Quotations')" :description="__('Create quotations from a validated enquiry. Each revision is kept as a version.')"
        :breadcrumbs="[__('Sales') => null, __('Quotations') => null]" />

    @if ($pendingApproval > 0 && auth()->user()->can('quotations.approve_discount'))
        <x-ui.alert tone="warning" class="mb-4">
            <button type="button" wire:click="$set('status', 'pending_approval')" class="font-medium underline">{{ trans_choice(':count quotation is waiting for discount approval.|:count quotations are waiting for discount approval.', $pendingApproval) }}</button>
        </x-ui.alert>
    @endif

    <x-ui.table :paginator="$quotations">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Quotation no, farmer or mobile…')" />
            <select wire:model.live="status" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Status') }}">
                <option value="">{{ __('All current') }}</option>
                @foreach ($statuses as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach
            </select>
        </x-slot:toolbar>
        <x-slot:head>
            <x-ui.th sortable="quotation_no" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Quotation') }}</x-ui.th>
            <x-ui.th>{{ __('Customer') }}</x-ui.th>
            <x-ui.th sortable="net_amount" :sort-by="$sortBy" :sort-direction="$sortDirection" align="right">{{ __('Net amount') }}</x-ui.th>
            <x-ui.th align="right">{{ __('Discount') }}</x-ui.th>
            <x-ui.th sortable="valid_until" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Valid until') }}</x-ui.th>
            <x-ui.th>{{ __('Status') }}</x-ui.th>
        </x-slot:head>
        @forelse ($quotations as $quotation)
            <tr wire:key="q-{{ $quotation->id }}" class="hover:bg-slate-50/70">
                <x-ui.td>
                    <a href="{{ route('sales.quotations.show', $quotation) }}" wire:navigate class="tabular font-semibold text-slate-900 hover:text-brand-700">{{ $quotation->reference() }}</a>
                    <p class="text-xs text-slate-400">{{ $quotation->enquiry->enquiry_no }} · {{ $quotation->preparedBy?->name }}</p>
                </x-ui.td>
                <x-ui.td>
                    <p class="font-medium text-slate-800">{{ $quotation->farmer->name }}</p>
                    <p class="tabular text-xs text-slate-500">{{ $quotation->farmer->mobile }}</p>
                </x-ui.td>
                <x-ui.td align="right" class="tabular font-medium">{{ Money::format($quotation->net_amount) }}</x-ui.td>
                <x-ui.td align="right" class="tabular text-xs">{{ $quotation->discount_percent }}%</x-ui.td>
                <x-ui.td class="whitespace-nowrap text-sm">
                    {{ $quotation->valid_until->format('d M Y') }}
                    @if ($quotation->isExpired())<x-ui.badge tone="rose" class="ml-1">{{ __('Expired') }}</x-ui.badge>@endif
                </x-ui.td>
                <x-ui.td><x-ui.badge :tone="$quotation->status->tone()" dot>{{ $quotation->status->label() }}</x-ui.badge></x-ui.td>
            </tr>
        @empty
            <x-ui.empty-row :colspan="6" :title="__('No quotations yet')" icon="document-text">{{ __('Open a validated enquiry and choose New quotation.') }}</x-ui.empty-row>
        @endforelse
    </x-ui.table>
</div>
