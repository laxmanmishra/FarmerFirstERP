@php use App\Support\Money; @endphp
<div>
    <x-ui.page-header :title="__('Orders')" :description="__('Bookings are created automatically when a deal is approved.')"
        :breadcrumbs="[__('Sales') => null, __('Orders') => null]">
        <x-slot:actions>
            @can('documents.view')
                <x-ui.button variant="secondary" icon="folder" :href="route('fulfilment.documents.index', ['tab' => 'requirements', 'blocking' => 1])" wire:navigate>
                    {{ trans_choice(':count document blocking delivery|:count documents blocking delivery', $blocking, ['count' => $blocking]) }}
                </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.table :paginator="$orders">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Order no, customer or mobile…')" />
            <select wire:model.live="stage" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Status') }}">
                <option value="">{{ __('Any status') }}</option>
                @foreach ($stages as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
            </select>
        </x-slot:toolbar>
        <x-slot:head>
            <x-ui.th sortable="order_no" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Order') }}</x-ui.th>
            <x-ui.th>{{ __('Customer') }}</x-ui.th>
            <x-ui.th sortable="order_value" :sort-by="$sortBy" :sort-direction="$sortDirection" align="right">{{ __('Value') }}</x-ui.th>
            <x-ui.th>{{ __('Tasks') }}</x-ui.th>
            <x-ui.th>{{ __('Documents') }}</x-ui.th>
            <x-ui.th sortable="expected_delivery_date" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Expected delivery') }}</x-ui.th>
            <x-ui.th>{{ __('Salesman') }}</x-ui.th>
            <x-ui.th>{{ __('Status') }}</x-ui.th>
        </x-slot:head>
        @forelse ($orders as $order)
            <tr wire:key="order-{{ $order->id }}" class="hover:bg-slate-50/70">
                <x-ui.td>
                    <a href="{{ route('sales.orders.show', $order) }}" wire:navigate class="tabular font-semibold text-slate-900 hover:text-brand-700">{{ $order->order_no }}</a>
                    <p class="text-xs text-slate-500">{{ $order->order_date->format('d M Y') }}</p>
                </x-ui.td>
                <x-ui.td>
                    <p class="font-medium text-slate-800">{{ $order->customer->name }}</p>
                    <p class="text-xs text-slate-500">{{ $order->customer->customer_no }} · {{ $order->customer->mobile }}</p>
                </x-ui.td>
                <x-ui.td align="right" class="tabular font-medium">{{ Money::format($order->order_value) }}</x-ui.td>
                <x-ui.td class="whitespace-nowrap">
                    @php $percent = $order->tasks_needed > 0 ? (int) round($order->tasks_done / $order->tasks_needed * 100) : 0; @endphp
                    <div class="flex items-center gap-2">
                        <div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-600" style="width: {{ $percent }}%"></div></div>
                        <span class="tabular text-xs text-slate-600">{{ $order->tasks_done }}/{{ $order->tasks_needed }}</span>
                    </div>
                </x-ui.td>
                <x-ui.td class="whitespace-nowrap text-sm">
                    @if ($order->documents_open > 0)
                        <x-ui.badge tone="amber">{{ __(':count open', ['count' => $order->documents_open]) }}</x-ui.badge>
                    @elseif ($order->documents_needed > 0)
                        <x-ui.badge tone="green">{{ __('Complete') }}</x-ui.badge>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </x-ui.td>
                <x-ui.td class="whitespace-nowrap text-sm">{{ $order->expected_delivery_date?->format('d M Y') ?? '—' }}</x-ui.td>
                <x-ui.td class="text-sm">{{ $order->primarySalesman?->name ?? '—' }}</x-ui.td>
                <x-ui.td class="whitespace-nowrap"><x-ui.stage-badge :stage="$order->stage" /></x-ui.td>
            </tr>
        @empty
            <x-ui.empty-row :colspan="8" :title="__('No orders yet')" icon="clipboard" />
        @endforelse
    </x-ui.table>
</div>
