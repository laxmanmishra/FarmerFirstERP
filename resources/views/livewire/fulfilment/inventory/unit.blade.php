@php use App\Enums\UnitStatus; use App\Support\Money; $allocation = $unit->activeAllocation; @endphp
<div>
    <x-ui.page-header :title="$unit->chassis_no" :description="$unit->product->displayName()"
        :breadcrumbs="[__('Fulfilment') => null, __('Inventory') => route('fulfilment.inventory.index'), $unit->chassis_no => null]">
        <x-slot:actions>
            @can('inventory.transfer')<x-ui.button variant="secondary" wire:click="open('transfer')">{{ __('Transfer') }}</x-ui.button>@endcan
            @can('inventory.reallocate')
                @if ($unit->status === UnitStatus::Available)<x-ui.button variant="danger-ghost" wire:click="open('block')">{{ __('Block') }}</x-ui.button>@endif
                @if ($unit->status === UnitStatus::Blocked)<x-ui.button variant="secondary" wire:click="open('unblock')">{{ __('Unblock') }}</x-ui.button>@endif
                @if ($allocation)
                    <x-ui.button variant="secondary" wire:click="open('reallocate')">{{ __('Swap unit') }}</x-ui.button>
                    <x-ui.button variant="danger-ghost" wire:click="open('release')">{{ __('Release') }}</x-ui.button>
                @endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($unit->status === UnitStatus::Blocked)
        <x-ui.alert tone="danger" class="mb-6" :title="__('Blocked')">{{ $unit->status_reason }}</x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <x-ui.card :title="__('Unit')">
            <dl class="space-y-3">
                <x-ui.dl-item :label="__('Status')"><x-ui.badge :tone="$unit->status->tone()">{{ $unit->status->label() }}</x-ui.badge></x-ui.dl-item>
                <x-ui.dl-item :label="__('Chassis no.')"><span class="tabular">{{ $unit->chassis_no }}</span></x-ui.dl-item>
                <x-ui.dl-item :label="__('Engine no.')"><span class="tabular">{{ $unit->engine_no }}</span></x-ui.dl-item>
                <x-ui.dl-item :label="__('Product')">{{ $unit->product->displayName() }}@if ($unit->variant) · {{ $unit->variant->name }}@endif</x-ui.dl-item>
                <x-ui.dl-item :label="__('Colour / year')">{{ collect([$unit->colour, $unit->model_year])->filter()->implode(' · ') }}</x-ui.dl-item>
                <x-ui.dl-item :label="__('Location')">{{ $unit->location->name }} · {{ $unit->location->branch->name }}</x-ui.dl-item>
                <x-ui.dl-item :label="__('Received')">{{ $unit->received_on->format('d M Y') }}@if ($unit->inward) · {{ $unit->inward->grn_no }}@endif</x-ui.dl-item>
                @can('inventory.inward')
                    @if ($unit->purchase_cost)<x-ui.dl-item :label="__('Purchase cost')">₹ {{ Money::format($unit->purchase_cost) }}</x-ui.dl-item>@endif
                @endcan
                @if ($allocation)
                    <x-ui.dl-item :label="__('Allocated to')">
                        <a href="{{ route('sales.orders.show', $allocation->order_id) }}" wire:navigate class="font-medium text-brand-700 hover:underline">{{ $allocation->order->order_no }}</a> · {{ $allocation->order->customer->name }}
                    </x-ui.dl-item>
                @endif
            </dl>
        </x-ui.card>

        <div class="space-y-6 xl:col-span-2">
            <x-ui.card :title="__('Allocation history')" :padding="false">
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($unit->allocations as $entry)
                        <li class="flex flex-wrap items-start justify-between gap-2 px-5 py-3">
                            <div>
                                <a href="{{ route('sales.orders.show', $entry->order_id) }}" wire:navigate class="tabular font-medium text-brand-700 hover:underline">{{ $entry->order->order_no }}</a>
                                <p class="text-xs text-slate-500">{{ __('Allocated :date by :name', ['date' => $entry->allocated_at->format('d M Y, H:i'), 'name' => $entry->allocator?->name]) }}</p>
                                @if ($entry->released_at)
                                    <p class="text-xs text-slate-500">{{ __('Released :date by :name: :reason', ['date' => $entry->released_at->format('d M Y, H:i'), 'name' => $entry->releaser?->name, 'reason' => $entry->release_reason]) }}</p>
                                @endif
                            </div>
                            <x-ui.badge :tone="$entry->isActive() ? 'brand' : 'slate'">{{ $entry->isActive() ? __('Active') : __('Released') }}</x-ui.badge>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-slate-500">{{ __('Never allocated.') }}</li>
                    @endforelse
                </ul>
            </x-ui.card>

            <x-ui.card :title="__('Stock movements')" :padding="false">
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($unit->movements as $movement)
                        <li class="px-5 py-3">
                            <div class="flex items-center justify-between gap-2">
                                <x-ui.badge :tone="$movement->type->tone()">{{ $movement->type->label() }}</x-ui.badge>
                                <span class="text-xs text-slate-400">{{ $movement->created_at->format('d M Y, H:i') }} · {{ $movement->user?->name }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-600">
                                @if ($movement->fromLocation || $movement->toLocation){{ $movement->fromLocation?->name ?? '—' }} → {{ $movement->toLocation?->name ?? '—' }}@endif
                                @if ($movement->order) · {{ $movement->order->order_no }}@endif
                                @if ($movement->remarks) · {{ $movement->remarks }}@endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        </div>
    </div>

    @if (in_array($modal, ['transfer', 'block', 'unblock', 'release', 'reallocate'], true))
        <x-ui.modal wire:model="modal" :tone="in_array($modal, ['block', 'release'], true) ? 'danger' : null"
            :title="match ($modal) { 'transfer' => __('Transfer unit'), 'block' => __('Block unit'), 'unblock' => __('Unblock unit'), 'release' => __('Release from order'), default => __('Swap allocated unit') }"
            :description="match ($modal) { 'release' => __('The unit returns to stock and the order needs a new one.'), 'reallocate' => __('The order keeps its allocation with the unit you choose; history keeps both.'), 'transfer' => __('Allocated units can move only within their branch.'), default => null }">
            <form id="unit-form" wire:submit="save" class="space-y-4">
                @if ($modal === 'transfer')
                    <x-ui.select :label="__('To location')" wire:model="form.location_id" name="form.location_id" :options="$locations" :placeholder="__('Choose…')" required />
                @elseif ($modal === 'reallocate')
                    <x-ui.select :label="__('Replacement unit')" wire:model="form.unit_id" name="form.unit_id" :options="$swapUnits" :placeholder="__('Choose…')" required />
                @endif
                <x-ui.textarea :label="$modal === 'transfer' || $modal === 'unblock' ? __('Remarks') : __('Reason')" wire:model="form.reason" name="form.reason" rows="2" :required="! in_array($modal, ['transfer', 'unblock'], true)" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="unit-form" wire:target="save">{{ __('Confirm') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
