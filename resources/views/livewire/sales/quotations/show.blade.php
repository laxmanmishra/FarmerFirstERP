@php use App\Enums\QuotationStatus; use App\Support\Money; @endphp
<div>
    <x-ui.page-header :title="$quotation->reference()" :description="$quotation->enquiry->farmer->name.' · '.$quotation->enquiry->enquiry_no"
        :breadcrumbs="[__('Sales') => null, __('Quotations') => route('sales.quotations.index'), $quotation->reference() => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="document-text" :href="route('sales.quotations.print', $quotation)" target="_blank">{{ __('Print / PDF') }}</x-ui.button>
            @can('quotations.create')
                @if ($quotation->status->isEditable())
                    <x-ui.button variant="secondary" icon="pencil" :href="route('sales.quotations.edit', $quotation)" wire:navigate>{{ __('Edit') }}</x-ui.button>
                @endif
                @if ($isLatest && in_array($quotation->status, [QuotationStatus::Draft, QuotationStatus::Issued, QuotationStatus::Declined], true))
                    <x-ui.button variant="secondary" icon="refresh" wire:click="revise" wire:confirm="{{ __('Create a new version? This version will be superseded.') }}">{{ __('Revise') }}</x-ui.button>
                @endif
                @if ($quotation->status === QuotationStatus::Draft)
                    <x-ui.button icon="check" wire:click="issue">{{ __('Issue to customer') }}</x-ui.button>
                @endif
                @if ($quotation->status === QuotationStatus::Issued)
                    <x-ui.button variant="danger-ghost" wire:click="open('decline')">{{ __('Customer declined') }}</x-ui.button>
                    <x-ui.button icon="check" wire:click="open('accept')" :disabled="$quotation->isExpired()">{{ __('Customer accepted') }}</x-ui.button>
                @endif
            @endcan
            @if ($quotation->status === QuotationStatus::PendingApproval && $canApprove)
                <x-ui.button icon="check-badge" wire:click="open('approve')">{{ __('Approve discount') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($quotation->status === QuotationStatus::PendingApproval)
        <x-ui.alert tone="warning" class="mb-6" :title="__('Discount approval pending')">
            {{ __('The discount of :amount (:percent%) is above the preparer\'s limit. A manager with sufficient authority must approve it before the quotation can be issued.', ['amount' => Money::format($quotation->discount_total), 'percent' => $quotation->discount_percent]) }}
        </x-ui.alert>
    @elseif ($quotation->isExpired())
        <x-ui.alert tone="warning" class="mb-6">{{ __('This quotation expired on :date. Revise it with a new validity date.', ['date' => $quotation->valid_until->format('d M Y')]) }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-ui.table>
                <x-slot:head>
                    <x-ui.th>{{ __('Item') }}</x-ui.th>
                    <x-ui.th align="right">{{ __('Qty') }}</x-ui.th>
                    <x-ui.th align="right">{{ __('Unit price') }}</x-ui.th>
                    <x-ui.th align="right">{{ __('Discount') }}</x-ui.th>
                    <x-ui.th align="right">{{ __('Tax') }}</x-ui.th>
                    <x-ui.th align="right">{{ __('Total') }}</x-ui.th>
                </x-slot:head>
                @foreach ($quotation->items as $item)
                    <tr wire:key="qi-{{ $item->id }}">
                        <x-ui.td>
                            <p class="font-medium text-slate-900">{{ $item->description }}</p>
                            <p class="text-xs text-slate-500">{{ $item->line_type->label() }}</p>
                        </x-ui.td>
                        <x-ui.td align="right" class="tabular">{{ $item->quantity }}</x-ui.td>
                        <x-ui.td align="right" class="tabular">{{ Money::format($item->unit_price) }}</x-ui.td>
                        <x-ui.td align="right" class="tabular text-rose-600">{{ Money::compare($item->discount_amount, 0) > 0 ? '−'.Money::format($item->discount_amount) : '—' }}</x-ui.td>
                        <x-ui.td align="right" class="tabular text-xs">{{ Money::format($item->tax_amount) }} <span class="text-slate-400">({{ $item->tax_percent }}%)</span></x-ui.td>
                        <x-ui.td align="right" class="tabular font-medium">{{ Money::format($item->line_total) }}</x-ui.td>
                    </tr>
                @endforeach
            </x-ui.table>

            @if ($quotation->terms)
                <x-ui.card :title="__('Terms & conditions')"><p class="whitespace-pre-line text-sm text-slate-700">{{ $quotation->terms }}</p></x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card :title="__('Status')">
                <x-ui.badge :tone="$quotation->status->tone()" dot>{{ $quotation->status->label() }}</x-ui.badge>
                <dl class="mt-4 space-y-3">
                    <x-ui.dl-item :label="__('Valid until')">{{ $quotation->valid_until->format('d M Y') }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Prepared by')">{{ $quotation->preparedBy?->name }} · {{ $quotation->created_at->format('d M Y') }}</x-ui.dl-item>
                    @if ($quotation->discountApprover)<x-ui.dl-item :label="__('Discount approved by')">{{ $quotation->discountApprover->name }} · {{ $quotation->discount_approved_at->format('d M Y H:i') }}</x-ui.dl-item>@endif
                    @if ($quotation->issued_at)<x-ui.dl-item :label="__('Issued')">{{ $quotation->issued_at->format('d M Y H:i') }}</x-ui.dl-item>@endif
                    @if ($quotation->decided_at)<x-ui.dl-item :label="__('Customer decision')">{{ $quotation->decided_at->format('d M Y') }}@if ($quotation->decision_remarks) — {{ $quotation->decision_remarks }}@endif</x-ui.dl-item>@endif
                    @if ($quotation->enquiry->deal)
                        <x-ui.dl-item :label="__('Deal')"><a href="{{ route('sales.deals.show', $quotation->enquiry->deal) }}" wire:navigate class="font-medium text-brand-700 hover:underline">{{ $quotation->enquiry->deal->deal_no }}</a></x-ui.dl-item>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('Amounts')">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Gross') }}</dt><dd class="tabular">{{ Money::format($quotation->gross_total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Discount') }} ({{ $quotation->discount_percent }}%)</dt><dd class="tabular text-rose-600">−{{ Money::format($quotation->discount_total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Tax') }}</dt><dd class="tabular">{{ Money::format($quotation->tax_total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Charges') }}</dt><dd class="tabular">{{ Money::format($quotation->charges_total) }}</dd></div>
                    @if (Money::compare($quotation->exchange_value, 0) > 0)
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Exchange') }}</dt><dd class="tabular text-rose-600">−{{ Money::format($quotation->exchange_value) }}</dd></div>
                    @endif
                    <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-semibold"><dt>{{ __('Net amount') }}</dt><dd class="tabular">{{ Money::format($quotation->net_amount) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Finance') }}</dt><dd class="tabular">{{ Money::format($quotation->finance_amount) }}</dd></div>
                    <div class="flex justify-between font-medium"><dt>{{ __('Customer contribution') }}</dt><dd class="tabular">{{ Money::format($quotation->customer_contribution) }}</dd></div>
                </dl>
            </x-ui.card>

            @if ($versions->count() > 1)
                <x-ui.card :title="__('Versions')" :padding="false">
                    <ul class="divide-y divide-slate-100">
                        @foreach ($versions as $version)
                            <li>
                                <a href="{{ route('sales.quotations.show', $version->id) }}" wire:navigate @class(['flex items-center justify-between px-5 py-2.5 text-sm hover:bg-slate-50', 'bg-brand-50/60' => $version->id === $quotation->id])>
                                    <span>v{{ $version->version }} · {{ $version->created_at->format('d M') }}</span>
                                    <span class="flex items-center gap-2"><span class="tabular">{{ Money::format($version->net_amount) }}</span><x-ui.badge :tone="$version->status->tone()">{{ $version->status->label() }}</x-ui.badge></span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>
    </div>

    @if (in_array($modal, ['approve', 'accept', 'decline'], true))
        <x-ui.modal wire:model="modal" :tone="$modal === 'decline' ? 'danger' : null"
            :title="['approve' => __('Approve discount'), 'accept' => __('Customer accepted'), 'decline' => __('Customer declined')][$modal]"
            :description="$modal === 'accept' ? __('Accepting supersedes the enquiry\'s other quotations and updates a draft deal with these figures.') : null">
            <form id="q-form" wire:submit="{{ $modal === 'approve' ? 'approveDiscount' : 'decide' }}">
                <x-ui.textarea :label="__('Remarks')" wire:model="remarks" name="remarks" rows="3" :required="$modal === 'decline'" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="q-form" :variant="$modal === 'decline' ? 'danger' : 'primary'">{{ __('Confirm') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
