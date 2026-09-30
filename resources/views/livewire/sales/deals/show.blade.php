@php use App\Enums\DealDecision; use App\Support\Money; $editable = $deal->isEditable() && auth()->user()->can('deals.update'); @endphp
<div>
    <x-ui.page-header :title="$deal->deal_no" :description="$deal->customer->name.' · '.$deal->customer->customer_no"
        :breadcrumbs="[__('Sales') => null, __('Deals') => route('sales.deals.index'), $deal->deal_no => null]">
        <x-slot:actions>
            @if ($editable)
                <x-ui.button icon="check" wire:click="submit" :disabled="$missing !== []">{{ __('Mark Deal Ready') }}</x-ui.button>
            @endif
            @if ($canDecide)
                <x-ui.button variant="danger-ghost" wire:click="openDecision('rejected')">{{ __('Reject') }}</x-ui.button>
                <x-ui.button variant="secondary" wire:click="openDecision('sent_back')">{{ __('Send back') }}</x-ui.button>
                <x-ui.button icon="check-badge" wire:click="openDecision('approved')">{{ __('Approve') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-(--radius-card) border border-slate-200 bg-white p-4 shadow-(--shadow-card)">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Status') }}</p>
            <div class="mt-2"><x-ui.stage-badge :stage="$deal->stage" /></div>
            @if ($deal->approved_at)<p class="mt-1 text-xs text-slate-500">{{ __('Approved :date', ['date' => $deal->approved_at->format('d M Y')]) }}</p>@endif
        </div>
        <x-ui.kpi-card :label="__('Deal value')" :value="Money::format($deal->deal_value)" icon="banknotes" />
        <x-ui.kpi-card :label="__('Customer contribution')" :value="Money::format($deal->customer_contribution)" icon="calculator" tone="sky" />
        <x-ui.kpi-card :label="__('Finance')" :value="$deal->finance_required ? Money::format($deal->finance_amount) : __('Cash deal')" icon="building" tone="amber" />
    </div>

    @if ($deal->isAwaitingApproval() && ! $canDecide && auth()->user()->can('deals.approve'))
        <x-ui.alert class="mb-6">{{ __('You submitted this deal or are its salesman, so another manager must decide it.') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @if ($deal->isEditable())
                <x-ui.card :title="__('Deal Ready checklist')">
                    @if ($missing === [])
                        <x-ui.alert tone="success">{{ __('Everything required is in place. Mark the deal ready to send it for approval.') }}</x-ui.alert>
                    @else
                        <ul class="space-y-2">
                            @foreach ($missing as $item)
                                <li class="flex items-start gap-2 text-sm text-slate-700"><x-ui.icon name="exclamation" class="mt-0.5 size-4 shrink-0 text-amber-500" /> {{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($acceptedQuotations->isNotEmpty() && $acceptedQuotations->doesntContain('id', $deal->quotation_id) && $editable)
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach ($acceptedQuotations as $accepted)
                                <x-ui.button variant="secondary" size="sm" wire:click="applyQuotation({{ $accepted->id }})">{{ __('Apply :no', ['no' => $accepted->reference()]) }}</x-ui.button>
                            @endforeach
                        </div>
                    @elseif ($deal->quotation === null)
                        <div class="mt-4">
                            @can('quotations.create')
                                <x-ui.button size="sm" icon="plus" :href="route('sales.quotations.create', ['enquiry' => $deal->enquiry_id])" wire:navigate>{{ __('Prepare quotation') }}</x-ui.button>
                            @endcan
                        </div>
                    @endif
                </x-ui.card>
            @endif

            <x-ui.table>
                <x-slot:toolbar>
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ __('Approved commercial snapshot') }}</p>
                        <p class="text-xs text-slate-500">
                            @if ($deal->quotation)
                                {{ __('From') }} <a href="{{ route('sales.quotations.show', $deal->quotation) }}" wire:navigate class="font-medium text-brand-700 hover:underline">{{ $deal->quotation->reference() }}</a>
                            @else
                                {{ __('No accepted quotation applied yet.') }}
                            @endif
                        </p>
                    </div>
                </x-slot:toolbar>
                <x-slot:head>
                    <x-ui.th>{{ __('Item') }}</x-ui.th>
                    <x-ui.th align="right">{{ __('Qty') }}</x-ui.th>
                    <x-ui.th align="right">{{ __('Rate') }}</x-ui.th>
                    <x-ui.th align="right">{{ __('Discount') }}</x-ui.th>
                    <x-ui.th align="right">{{ __('Total') }}</x-ui.th>
                </x-slot:head>
                @forelse ($deal->items as $item)
                    <tr wire:key="di-{{ $item->id }}">
                        <x-ui.td>{{ $item->description }} <span class="text-xs text-slate-400">· {{ $item->line_type->label() }}</span></x-ui.td>
                        <x-ui.td align="right" class="tabular">{{ $item->quantity }}</x-ui.td>
                        <x-ui.td align="right" class="tabular">{{ Money::format($item->unit_price) }}</x-ui.td>
                        <x-ui.td align="right" class="tabular">{{ Money::compare($item->discount_amount, 0) > 0 ? '−'.Money::format($item->discount_amount) : '—' }}</x-ui.td>
                        <x-ui.td align="right" class="tabular font-medium">{{ Money::format($item->line_total) }}</x-ui.td>
                    </tr>
                @empty
                    <x-ui.empty-row :colspan="5" :title="__('No lines yet')" icon="document-text" />
                @endforelse
                @if ($deal->items->isNotEmpty())
                    <tr class="bg-slate-50/60 text-sm">
                        <td colspan="4" class="px-4 py-2 text-right text-slate-500">{{ __('Exchange value') }}</td>
                        <td class="tabular px-4 py-2 text-right">−{{ Money::format($deal->exchange_value) }}</td>
                    </tr>
                    <tr class="bg-slate-50/60 font-semibold">
                        <td colspan="4" class="px-4 py-2 text-right">{{ __('Deal value') }}</td>
                        <td class="tabular px-4 py-2 text-right">{{ Money::format($deal->deal_value) }}</td>
                    </tr>
                @endif
            </x-ui.table>

            <x-ui.card :title="__('Terms & fulfilment')" :description="__('These flags decide which fulfilment departments get tasks when the order is created.')">
                <form wire:submit="saveTerms" class="space-y-5">
                    <fieldset @disabled(! $editable) class="space-y-5">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-ui.input type="date" :label="__('Expected delivery')" wire:model="terms.expected_delivery_date" name="terms.expected_delivery_date" required />
                            <x-ui.input :label="__('Booking amount (₹)')" wire:model="terms.booking_amount" name="terms.booking_amount" inputmode="decimal" />
                            <div>
                                <x-ui.checkbox :label="__('Finance required')" wire:model.live="terms.finance_required" />
                                @if ($terms['finance_required'])
                                    <div class="mt-2"><x-ui.input :label="__('Finance amount (₹)')" wire:model="terms.finance_amount" name="terms.finance_amount" inputmode="decimal" /></div>
                                @endif
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <x-ui.checkbox :label="__('RTO registration')" wire:model="terms.rto_required" />
                            <x-ui.checkbox :label="__('Insurance')" wire:model="terms.insurance_required" />
                            <x-ui.checkbox :label="__('PDI inspection')" wire:model="terms.pdi_required" />
                        </div>
                        <x-ui.textarea :label="__('Remarks')" wire:model="terms.remarks" name="terms.remarks" rows="2" />
                    </fieldset>
                    @if ($editable)
                        <div class="flex justify-end"><x-ui.button type="submit" variant="secondary" wire:target="saveTerms">{{ __('Save terms') }}</x-ui.button></div>
                    @endif
                </form>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card :title="__('Links')">
                <dl class="space-y-3">
                    <x-ui.dl-item :label="__('Customer')"><a href="{{ route('sales.customers.show', $deal->customer) }}" wire:navigate class="font-medium text-brand-700 hover:underline">{{ $deal->customer->customer_no }} · {{ $deal->customer->name }}</a></x-ui.dl-item>
                    <x-ui.dl-item :label="__('Enquiry')"><a href="{{ route('crm.enquiries.show', $deal->enquiry_id) }}" wire:navigate class="text-brand-700 hover:underline">{{ $deal->enquiry->enquiry_no }}</a></x-ui.dl-item>
                    <x-ui.dl-item :label="__('Primary salesman')">{{ $deal->primarySalesman?->name }}</x-ui.dl-item>
                    <x-ui.dl-item :label="__('Branch')">{{ $deal->branch->name }}</x-ui.dl-item>
                    @if ($deal->enquiry->exchangeTractor)
                        <x-ui.dl-item :label="__('Exchange tractor')">
                            {{ $deal->enquiry->exchangeTractor->brand_name }} {{ $deal->enquiry->exchangeTractor->model_name }}
                            <span class="block text-xs text-slate-500">{{ __('Approved value: :v', ['v' => $deal->enquiry->exchangeTractor->approved_exchange_value ? Money::format($deal->enquiry->exchangeTractor->approved_exchange_value) : __('on approval')]) }}</span>
                        </x-ui.dl-item>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('Approval history')" :padding="false">
                <ul class="divide-y divide-slate-100">
                    @forelse ($deal->approvals as $approval)
                        <li class="px-5 py-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <x-ui.badge :tone="$approval->action->tone()">{{ $approval->action->label() }}</x-ui.badge>
                                <span class="text-xs text-slate-400">{{ $approval->created_at->format('d M Y, H:i') }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ $approval->user?->name }} · {{ __('value :v', ['v' => Money::format($approval->snapshot['deal_value'] ?? 0)]) }}</p>
                            @if ($approval->remarks)<p class="mt-1 text-slate-700">{{ $approval->remarks }}</p>@endif
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-slate-500">{{ __('Not submitted yet.') }}</li>
                    @endforelse
                </ul>
            </x-ui.card>

            <x-ui.card :title="__('Quotations')" :padding="false">
                <ul class="divide-y divide-slate-100">
                    @forelse ($quotations as $quotation)
                        <li><a href="{{ route('sales.quotations.show', $quotation) }}" wire:navigate class="flex items-center justify-between px-5 py-2.5 text-sm hover:bg-slate-50">
                            <span class="tabular">{{ $quotation->reference() }}</span>
                            <x-ui.badge :tone="$quotation->status->tone()">{{ $quotation->status->label() }}</x-ui.badge>
                        </a></li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-slate-500">{{ __('No quotations.') }}</li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>
    </div>

    @if (in_array($modal, ['approved', 'sent_back', 'rejected'], true))
        @php $decision = DealDecision::from($modal); @endphp
        <x-ui.modal wire:model="modal" :tone="$decision === DealDecision::Rejected ? 'danger' : null"
            :title="__(':decision deal :no', ['decision' => $decision === DealDecision::Approved ? __('Approve') : ($decision === DealDecision::SentBack ? __('Send back') : __('Reject')), 'no' => $deal->deal_no])"
            :description="$decision === DealDecision::Approved ? __('The approved figures become the order\'s commercial terms.') : ($decision === DealDecision::SentBack ? __('The salesman can correct the deal and submit again.') : __('Rejecting closes the deal.'))">
            <form id="decision-form" wire:submit="decide">
                <x-ui.textarea :label="__('Remarks')" wire:model="decisionRemarks" name="decisionRemarks" rows="3" :required="$decision !== DealDecision::Approved" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="decision-form" :variant="$decision === DealDecision::Rejected ? 'danger' : 'primary'" wire:target="decide">{{ __('Confirm') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
