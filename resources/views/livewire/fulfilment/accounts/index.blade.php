@php use App\Enums\AccountPosition; use App\Support\Money; @endphp
<div>
    <x-ui.page-header :title="__('Accounts')" :description="__('One account file per order. Balances are computed from cleared payments; nothing is typed in by hand.')"
        :breadcrumbs="[__('Fulfilment') => null, __('Accounts') => null]" />

    @if (count($tabs) > 1)
        <x-ui.tabs class="mb-6" :active="$tab" :tabs="$tabs" />
    @endif

    @if ($tab === 'verification')
        <x-ui.table :paginator="$payments">
            <x-slot:head>
                <x-ui.th>{{ __('Payment') }}</x-ui.th>
                <x-ui.th>{{ __('Account file') }}</x-ui.th>
                <x-ui.th>{{ __('Mode') }}</x-ui.th>
                <x-ui.th align="right">{{ __('Amount') }}</x-ui.th>
                <x-ui.th>{{ __('Recorded by') }}</x-ui.th>
                <x-ui.th>{{ __('Status') }}</x-ui.th>
            </x-slot:head>
            @forelse ($payments as $payment)
                <tr wire:key="p-{{ $payment->id }}" class="hover:bg-slate-50/70">
                    <x-ui.td><span class="tabular font-medium">{{ $payment->payment_no }}</span><p class="text-xs text-slate-500">{{ $payment->received_on->format('d M Y') }}</p></x-ui.td>
                    <x-ui.td>
                        <a href="{{ route('fulfilment.accounts.show', $payment->account_file_id) }}" wire:navigate class="tabular font-semibold text-slate-900 hover:text-brand-700">{{ $payment->accountFile->file_no }}</a>
                        <p class="text-xs text-slate-500">{{ $payment->accountFile->order->customer->name }} · {{ $payment->accountFile->order->order_no }}</p>
                    </x-ui.td>
                    <x-ui.td class="text-sm">{{ $payment->mode->label() }} @if ($payment->reference_no)<span class="block text-xs text-slate-500">{{ $payment->reference_no }}</span>@endif</x-ui.td>
                    <x-ui.td align="right" class="tabular font-medium">{{ Money::format($payment->amount) }}</x-ui.td>
                    <x-ui.td class="text-sm">{{ $payment->recorder?->name }}</x-ui.td>
                    <x-ui.td><x-ui.badge :tone="$payment->status->tone()">{{ $payment->status->label() }}</x-ui.badge></x-ui.td>
                </tr>
            @empty
                <x-ui.empty-row :colspan="6" :title="__('No payments waiting')" icon="check-badge" />
            @endforelse
        </x-ui.table>
    @elseif ($tab === 'refunds')
        <x-ui.table :paginator="$refunds">
            <x-slot:head>
                <x-ui.th>{{ __('Refund') }}</x-ui.th>
                <x-ui.th>{{ __('Account file') }}</x-ui.th>
                <x-ui.th align="right">{{ __('Amount') }}</x-ui.th>
                <x-ui.th>{{ __('Reason') }}</x-ui.th>
                <x-ui.th>{{ __('Requested by') }}</x-ui.th>
                <x-ui.th>{{ __('Status') }}</x-ui.th>
            </x-slot:head>
            @forelse ($refunds as $refund)
                <tr wire:key="r-{{ $refund->id }}" class="hover:bg-slate-50/70">
                    <x-ui.td class="tabular font-medium">{{ $refund->refund_no }}</x-ui.td>
                    <x-ui.td>
                        <a href="{{ route('fulfilment.accounts.show', $refund->account_file_id) }}" wire:navigate class="tabular font-semibold text-slate-900 hover:text-brand-700">{{ $refund->accountFile->file_no }}</a>
                        <p class="text-xs text-slate-500">{{ $refund->accountFile->order->customer->name }}</p>
                    </x-ui.td>
                    <x-ui.td align="right" class="tabular font-medium">{{ Money::format($refund->amount) }}</x-ui.td>
                    <x-ui.td class="max-w-xs text-sm">{{ $refund->reason }}</x-ui.td>
                    <x-ui.td class="text-sm">{{ $refund->requester?->name }}</x-ui.td>
                    <x-ui.td><x-ui.badge :tone="$refund->status->tone()">{{ $refund->status->label() }}</x-ui.badge></x-ui.td>
                </tr>
            @empty
                <x-ui.empty-row :colspan="6" :title="__('No refunds pending')" icon="banknotes" />
            @endforelse
        </x-ui.table>
    @else
        <x-ui.table :paginator="$files">
            <x-slot:toolbar>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('File, order, customer or mobile…')" />
                    <select wire:model.live="stage" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Status') }}">
                        <option value="">{{ __('Any status') }}</option>
                        @foreach ($stages as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
                    </select>
                    <select wire:model.live="position" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Payment position') }}">
                        <option value="">{{ __('Any position') }}</option>
                        <option value="short">{{ __('Short / not paid') }}</option>
                        <option value="cleared">{{ __('Fully cleared') }}</option>
                        <option value="excess">{{ __('Excess received') }}</option>
                    </select>
                    <x-ui.checkbox :label="__('Open only')" wire:model.live="open" />
                </div>
            </x-slot:toolbar>
            <x-slot:head>
                <x-ui.th sortable="file_no" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('File') }}</x-ui.th>
                <x-ui.th>{{ __('Customer') }}</x-ui.th>
                <x-ui.th sortable="receivable_amount" :sort-by="$sortBy" :sort-direction="$sortDirection" align="right">{{ __('Receivable') }}</x-ui.th>
                <x-ui.th align="right">{{ __('Cleared') }}</x-ui.th>
                <x-ui.th align="right">{{ __('Balance') }}</x-ui.th>
                <x-ui.th>{{ __('Position') }}</x-ui.th>
                <x-ui.th>{{ __('Status') }}</x-ui.th>
            </x-slot:head>
            @forelse ($files as $file)
                @php $cleared = Money::normalise($file->cleared_total); $position = AccountPosition::of($file->receivable_amount, $cleared); @endphp
                <tr wire:key="af-{{ $file->id }}" class="hover:bg-slate-50/70">
                    <x-ui.td>
                        <a href="{{ route('fulfilment.accounts.show', $file) }}" wire:navigate class="tabular font-semibold text-slate-900 hover:text-brand-700">{{ $file->file_no }}</a>
                        <p class="tabular text-xs text-slate-500">{{ $file->order->order_no }}@if ($file->order->finance_required) · {{ __('financed') }}@endif</p>
                    </x-ui.td>
                    <x-ui.td><p class="font-medium text-slate-800">{{ $file->order->customer->name }}</p><p class="text-xs text-slate-500">{{ $file->order->customer->mobile }}</p></x-ui.td>
                    <x-ui.td align="right" class="tabular">{{ Money::format($file->receivable_amount) }}</x-ui.td>
                    <x-ui.td align="right" class="tabular">{{ Money::format($cleared) }}@if ($file->uncleared_count > 0)<span class="block text-xs text-amber-700">{{ __(':n uncleared', ['n' => $file->uncleared_count]) }}</span>@endif</x-ui.td>
                    <x-ui.td align="right" class="tabular font-medium">{{ Money::format(Money::sub($file->receivable_amount, $cleared)) }}</x-ui.td>
                    <x-ui.td><x-ui.badge :tone="$position->tone()">{{ $position->label() }}</x-ui.badge></x-ui.td>
                    <x-ui.td class="whitespace-nowrap"><x-ui.stage-badge :stage="$file->stage" /></x-ui.td>
                </tr>
            @empty
                <x-ui.empty-row :colspan="7" :title="__('No account files')" icon="calculator" />
            @endforelse
        </x-ui.table>
    @endif
</div>
