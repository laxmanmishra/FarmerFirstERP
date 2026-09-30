@php use App\Enums\PaymentKind; use App\Enums\PaymentStatus; use App\Enums\RefundStatus; use App\Support\Money; $position = $file->position(); @endphp
<div>
    <x-ui.page-header :title="$file->file_no" :description="$file->order->customer->name.' · '.$file->order->customer->mobile"
        :breadcrumbs="[__('Fulfilment') => null, __('Accounts') => route('fulfilment.accounts.index'), $file->file_no => null]">
        <x-slot:actions>
            @if (Money::compare($file->refundable(), 0) > 0)
                @can('accounts.record_payment')<x-ui.button variant="secondary" wire:click="open('refund')">{{ __('Request refund') }}</x-ui.button>@endcan
            @endif
            @if ($open)
                @can('accounts.clear_payment')
                    <x-ui.button variant="secondary" icon="user" wire:click="open('assign')">{{ __('Assign') }}</x-ui.button>
                    <x-ui.button variant="secondary" icon="refresh" wire:click="open('status')">{{ __('Update status') }}</x-ui.button>
                @endcan
            @endif
            @if (! $file->order->isCancelled())
                @can('accounts.record_payment')<x-ui.button icon="plus" wire:click="open('record')">{{ __('Record payment') }}</x-ui.button>@endcan
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-(--radius-card) border border-slate-200 bg-white p-4 shadow-(--shadow-card)">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Status') }}</p>
            <div class="mt-2"><x-ui.stage-badge :stage="$file->stage" /></div>
            <div class="mt-2"><x-ui.badge :tone="$position->tone()">{{ $position->label() }}</x-ui.badge></div>
        </div>
        <x-ui.kpi-card :label="__('Receivable')" :value="Money::format($file->receivable_amount)" icon="banknotes"
            :hint="__('Customer :c · Finance :f', ['c' => Money::format($file->customer_share), 'f' => Money::format($file->finance_share)])" />
        <x-ui.kpi-card :label="__('Cleared')" :value="Money::format($file->clearedTotal())" icon="check-badge" tone="sky" />
        <x-ui.kpi-card :label="__('Received, not cleared')" :value="Money::format($file->unclearedTotal())" icon="clock" tone="amber" />
        <x-ui.kpi-card :label="__('Balance')" :value="Money::format($file->balance())" icon="calculator" :tone="Money::compare($file->balance(), 0) > 0 ? 'rose' : 'brand'" />
    </div>

    @if ($file->order->financeFile)
        <x-ui.alert class="mb-6">
            {{ __('Finance: :financer — :stage. Record the financer\'s money as a finance-disbursement payment when it arrives.', ['financer' => $file->order->financeFile->financer?->name ?? __('financer not chosen yet'), 'stage' => $file->order->financeFile->stage->name]) }}
            @can('finance.view')<a href="{{ route('fulfilment.finance.show', $file->order->financeFile) }}" wire:navigate class="font-medium underline">{{ $file->order->financeFile->file_no }}</a>@endcan
        </x-ui.alert>
    @endif

    <x-ui.tabs class="mb-6" :active="$tab" :tabs="['ledger' => __('Payments & refunds'), 'documents' => __('Documents'), 'timeline' => __('Timeline')]" />

    @if ($tab === 'ledger')
        <x-ui.table class="mb-6">
            <x-slot:toolbar>
                <p class="text-sm font-semibold text-slate-900">{{ __('Payment ledger') }}</p>
                <p class="text-xs text-slate-500">{{ __('Entries are never edited: bounces are marked returned, corrections are reversal entries.') }}</p>
            </x-slot:toolbar>
            <x-slot:head>
                <x-ui.th>{{ __('Payment') }}</x-ui.th>
                <x-ui.th>{{ __('From') }}</x-ui.th>
                <x-ui.th>{{ __('Mode / reference') }}</x-ui.th>
                <x-ui.th align="right">{{ __('Amount') }}</x-ui.th>
                <x-ui.th>{{ __('Status') }}</x-ui.th>
                <x-ui.th>{{ __('Receipt') }}</x-ui.th>
                <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
            </x-slot:head>
            @forelse ($file->payments as $payment)
                <tr wire:key="pay-{{ $payment->id }}" @class(['hover:bg-slate-50/70', 'bg-slate-50/60' => $payment->kind !== PaymentKind::Receipt])>
                    <x-ui.td>
                        <span class="tabular font-medium">{{ $payment->payment_no }}</span>
                        <p class="text-xs text-slate-500">{{ $payment->received_on->format('d M Y') }} · {{ $payment->recorder?->name }}</p>
                        @if ($payment->kind !== PaymentKind::Receipt)<x-ui.badge tone="violet">{{ $payment->kind->label() }}@if ($payment->reverses) {{ __('of :no', ['no' => $payment->reverses->payment_no]) }}@endif</x-ui.badge>@endif
                    </x-ui.td>
                    <x-ui.td class="text-sm">{{ $payment->payer_type->label() }}</x-ui.td>
                    <x-ui.td class="text-sm">{{ $payment->mode->label() }}<span class="block text-xs text-slate-500">{{ collect([$payment->reference_no, $payment->bank_name])->filter()->implode(' · ') }}</span></x-ui.td>
                    <x-ui.td align="right" @class(['tabular font-medium', 'text-rose-700' => Money::isNegative($payment->amount)])>{{ Money::format($payment->amount) }}</x-ui.td>
                    <x-ui.td>
                        <x-ui.badge :tone="$payment->status->tone()">{{ $payment->status->label() }}</x-ui.badge>
                        @if ($payment->status_reason)<p class="mt-1 max-w-xs text-xs text-slate-500">{{ $payment->status_reason }}</p>@endif
                    </x-ui.td>
                    <x-ui.td class="text-sm">
                        @if ($payment->receipt)
                            <a href="{{ route('fulfilment.accounts.receipt', $payment->receipt) }}" target="_blank" class="tabular text-brand-700 hover:underline">{{ $payment->receipt->receipt_no }}</a>
                            @if ($payment->receipt->cancelled_at)<span class="block text-xs text-rose-700">{{ __('cancelled') }}</span>@endif
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </x-ui.td>
                    <x-ui.td align="right" class="whitespace-nowrap">
                        @if ($payment->status === PaymentStatus::PendingVerification)
                            @can('accounts.verify_payment')
                                @if ($payment->recorded_by !== $userId)
                                    <x-ui.button size="sm" icon="check" wire:click="verify({{ $payment->id }})">{{ __('Verify') }}</x-ui.button>
                                @endif
                                <x-ui.button size="sm" variant="danger-ghost" wire:click="open('reject', {{ $payment->id }})">{{ __('Reject') }}</x-ui.button>
                            @endcan
                        @elseif ($payment->status === PaymentStatus::Verified)
                            @can('accounts.clear_payment')<x-ui.button size="sm" wire:click="clear({{ $payment->id }})">{{ __('Mark cleared') }}</x-ui.button>@endcan
                        @endif
                        @if (in_array($payment->status, [PaymentStatus::Verified, PaymentStatus::Cleared], true) && $payment->mode->canBounce())
                            @can('accounts.clear_payment')<x-ui.button size="sm" variant="danger-ghost" wire:click="open('return', {{ $payment->id }})">{{ __('Bounced') }}</x-ui.button>@endcan
                        @endif
                        @if ($payment->status === PaymentStatus::Cleared && $payment->kind === PaymentKind::Receipt && $payment->recorded_by !== $userId)
                            @can('accounts.reverse')<x-ui.button size="sm" variant="ghost" wire:click="open('reverse', {{ $payment->id }})">{{ __('Reverse') }}</x-ui.button>@endcan
                        @endif
                    </x-ui.td>
                </tr>
            @empty
                <x-ui.empty-row :colspan="7" :title="__('No payments yet')" icon="banknotes" />
            @endforelse
        </x-ui.table>

        @if ($file->refunds->isNotEmpty())
            <x-ui.table>
                <x-slot:toolbar><p class="text-sm font-semibold text-slate-900">{{ __('Refunds') }}</p></x-slot:toolbar>
                <x-slot:head>
                    <x-ui.th>{{ __('Refund') }}</x-ui.th>
                    <x-ui.th align="right">{{ __('Amount') }}</x-ui.th>
                    <x-ui.th>{{ __('Reason') }}</x-ui.th>
                    <x-ui.th>{{ __('Requested / decided') }}</x-ui.th>
                    <x-ui.th>{{ __('Status') }}</x-ui.th>
                    <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
                </x-slot:head>
                @foreach ($file->refunds as $refund)
                    <tr wire:key="ref-{{ $refund->id }}">
                        <x-ui.td class="tabular font-medium">{{ $refund->refund_no }}</x-ui.td>
                        <x-ui.td align="right" class="tabular">{{ Money::format($refund->amount) }}</x-ui.td>
                        <x-ui.td class="max-w-xs text-sm">{{ $refund->reason }}@if ($refund->decision_remarks)<span class="block text-xs text-slate-500">{{ $refund->decision_remarks }}</span>@endif</x-ui.td>
                        <x-ui.td class="text-xs text-slate-500">{{ $refund->requester?->name }}@if ($refund->decider) → {{ $refund->decider->name }}@endif</x-ui.td>
                        <x-ui.td><x-ui.badge :tone="$refund->status->tone()">{{ $refund->status->label() }}</x-ui.badge></x-ui.td>
                        <x-ui.td align="right" class="whitespace-nowrap">
                            @if ($refund->status === RefundStatus::Requested && $refund->requested_by !== $userId)
                                @can('accounts.approve_refund')
                                    <x-ui.button size="sm" wire:click="approveRefund({{ $refund->id }})">{{ __('Approve') }}</x-ui.button>
                                    <x-ui.button size="sm" variant="danger-ghost" wire:click="open('refund_reject', {{ $refund->id }})">{{ __('Reject') }}</x-ui.button>
                                @endcan
                            @elseif ($refund->status === RefundStatus::Approved)
                                @can('accounts.clear_payment')<x-ui.button size="sm" wire:click="open('refund_pay', {{ $refund->id }})">{{ __('Mark paid') }}</x-ui.button>@endcan
                            @endif
                        </x-ui.td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    @elseif ($tab === 'documents')
        <livewire:fulfilment.documents.checklist :order-id="$file->order_id" :department-id="$departmentId" :key="'acc-docs-'.$file->id" />
    @else
        <x-ui.card><x-ui.timeline :items="$timeline" /></x-ui.card>
    @endif

    @if ($modal === 'record')
        <x-ui.modal wire:model="modal" width="max-w-2xl" :title="__('Record payment')" :description="__('Recorded as Verification pending. A different person verifies it, which issues the receipt.')">
            <form id="pay-form" wire:submit="recordPayment" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.select :label="__('From')" wire:model.live="form.payer_type" name="form.payer_type" :options="['customer' => __('Customer'), 'financer' => __('Financer')]" />
                    <x-ui.select :label="__('Mode')" wire:model.live="form.mode" name="form.mode" :options="$modes" />
                    <x-ui.input :label="__('Amount (₹)')" wire:model="form.amount" name="form.amount" inputmode="decimal" required />
                </div>
                @if (($form['mode'] ?? 'cash') !== 'cash')
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.input :label="__('Reference / UTR / cheque no.')" wire:model="form.reference_no" name="form.reference_no" required />
                        <x-ui.input type="date" :label="__('Instrument date')" wire:model="form.instrument_date" name="form.instrument_date" />
                        <x-ui.input :label="__('Bank')" wire:model="form.bank_name" name="form.bank_name" />
                    </div>
                @endif
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.input type="date" :label="__('Received on')" wire:model="form.received_on" name="form.received_on" required />
                    <div class="sm:col-span-2"><x-ui.input :label="__('Remarks')" wire:model="form.remarks" name="form.remarks" /></div>
                </div>
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="pay-form" wire:target="recordPayment">{{ __('Record') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @elseif (in_array($modal, ['reject', 'return', 'reverse', 'refund_reject'], true))
        <x-ui.modal wire:model="modal" tone="danger"
            :title="match ($modal) { 'reject' => __('Reject payment'), 'return' => __('Mark as bounced / returned'), 'reverse' => __('Reverse payment'), default => __('Reject refund') }"
            :description="match ($modal) { 'reverse' => __('A negative entry referencing the original is added; the original stays in the ledger.'), 'return' => __('The receipt is cancelled and the balance goes back up.'), default => null }">
            <form id="reason-form" wire:submit="{{ $modal === 'refund_reject' ? 'rejectRefund' : 'applyWithReason' }}">
                <x-ui.textarea :label="__('Reason')" wire:model="form.reason" name="form.reason" rows="3" required />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="reason-form" variant="danger">{{ __('Confirm') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @elseif ($modal === 'status')
        <x-ui.modal wire:model="modal" :title="__('Update accounts status')" :description="__('Completion is refused while money is short or payments are uncleared.')">
            <form id="acc-status-form" wire:submit="saveStatus" class="space-y-4">
                <x-ui.select :label="__('New status')" wire:model="form.stage_id" name="form.stage_id" :options="$targets->pluck('name', 'id')" :placeholder="__('Choose…')" required />
                <x-ui.textarea :label="__('Remarks')" wire:model="form.remarks" name="form.remarks" rows="2" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="acc-status-form" wire:target="saveStatus">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @elseif ($modal === 'assign')
        <x-ui.modal wire:model="modal" :title="__('Assign account file')">
            <form id="acc-assign-form" wire:submit="saveAssignment">
                <x-ui.select :label="__('Responsible employee')" wire:model="form.employee_id" name="form.employee_id" :options="$employees" :placeholder="__('Unassigned')" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="acc-assign-form" wire:target="saveAssignment">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @elseif ($modal === 'refund')
        <x-ui.modal wire:model="modal" :title="__('Request refund')" :description="__('Up to ₹:max can be refunded. Someone else approves it.', ['max' => Money::format($file->refundable())])">
            <form id="refund-form" wire:submit="requestRefund" class="space-y-4">
                <x-ui.input :label="__('Amount (₹)')" wire:model="form.amount" name="form.amount" inputmode="decimal" required />
                <x-ui.textarea :label="__('Reason')" wire:model="form.reason" name="form.reason" rows="2" required />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="refund-form" wire:target="requestRefund">{{ __('Request') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @elseif ($modal === 'refund_pay')
        <x-ui.modal wire:model="modal" :title="__('Pay refund')">
            <form id="refund-pay-form" wire:submit="payRefund" class="space-y-4">
                <x-ui.select :label="__('Paid by')" wire:model="form.mode" name="form.mode" :options="$refundModes" />
                <x-ui.input :label="__('Reference / UTR')" wire:model="form.reference_no" name="form.reference_no" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="refund-pay-form" wire:target="payRefund">{{ __('Mark paid') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
