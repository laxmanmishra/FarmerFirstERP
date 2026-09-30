@php use App\Enums\LineType; use App\Enums\UnitStatus; @endphp
<div>
    <x-ui.page-header :title="__('Inventory')" :description="__('Every physical unit is tracked by chassis and engine number. A unit can be allocated to only one order at a time.')"
        :breadcrumbs="[__('Fulfilment') => null, __('Inventory') => null]">
        <x-slot:actions>
            @can('inventory.inward')<x-ui.button icon="plus" wire:click="openGrn">{{ __('Receive stock (GRN)') }}</x-ui.button>@endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        @foreach (UnitStatus::cases() as $case)
            <button type="button" wire:click="$set('status', '{{ $status === $case->value ? '' : $case->value }}'); $set('tab', 'stock')"
                @class(['rounded-(--radius-card) border bg-white p-4 text-left shadow-(--shadow-card) transition hover:border-brand-300', 'border-brand-500 ring-1 ring-brand-500' => $status === $case->value, 'border-slate-200' => $status !== $case->value])>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $case->label() }}</p>
                <p class="tabular mt-1 text-2xl font-semibold text-slate-900">{{ $counts[$case->value] ?? 0 }}</p>
            </button>
        @endforeach
    </div>

    <x-ui.tabs class="mb-6" :active="$tab" :tabs="$tabs" />

    @if ($tab === 'allocation')
        <x-ui.table :paginator="$orders">
            <x-slot:head>
                <x-ui.th>{{ __('Order') }}</x-ui.th>
                <x-ui.th>{{ __('Customer') }}</x-ui.th>
                <x-ui.th>{{ __('Needs') }}</x-ui.th>
                <x-ui.th>{{ __('Allocated') }}</x-ui.th>
                <x-ui.th>{{ __('Expected delivery') }}</x-ui.th>
                <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
            </x-slot:head>
            @forelse ($orders as $order)
                <tr wire:key="ao-{{ $order->id }}" class="hover:bg-slate-50/70">
                    <x-ui.td><a href="{{ route('sales.orders.show', ['order' => $order, 'tab' => 'fulfilment']) }}" wire:navigate class="tabular font-semibold text-slate-900 hover:text-brand-700">{{ $order->order_no }}</a></x-ui.td>
                    <x-ui.td class="text-sm">{{ $order->customer->name }} <span class="block text-xs text-slate-500">{{ $order->customer->mobile }}</span></x-ui.td>
                    <x-ui.td class="text-sm">
                        @foreach ($order->items->where('line_type', LineType::Product) as $item)
                            <p>{{ $item->quantity }} × {{ $item->product?->displayName() ?? $item->description }}</p>
                        @endforeach
                    </x-ui.td>
                    <x-ui.td class="tabular text-sm">{{ $order->active_allocations_count }} / {{ $order->unitsRequired() }}</x-ui.td>
                    <x-ui.td class="whitespace-nowrap text-sm">{{ $order->expected_delivery_date?->format('d M Y') ?? '—' }}</x-ui.td>
                    <x-ui.td align="right">@can('inventory.allocate')<x-ui.button size="sm" wire:click="openAllocate({{ $order->id }})">{{ __('Allocate') }}</x-ui.button>@endcan</x-ui.td>
                </tr>
            @empty
                <x-ui.empty-row :colspan="6" :title="__('Every order has its units')" icon="cube" />
            @endforelse
        </x-ui.table>
    @elseif ($tab === 'grn')
        <x-ui.table :paginator="$inwards">
            <x-slot:head>
                <x-ui.th>{{ __('GRN') }}</x-ui.th>
                <x-ui.th>{{ __('Supplier') }}</x-ui.th>
                <x-ui.th>{{ __('Invoice') }}</x-ui.th>
                <x-ui.th>{{ __('Location') }}</x-ui.th>
                <x-ui.th align="right">{{ __('Units') }}</x-ui.th>
                <x-ui.th>{{ __('Received') }}</x-ui.th>
            </x-slot:head>
            @forelse ($inwards as $inward)
                <tr wire:key="grn-{{ $inward->id }}" class="hover:bg-slate-50/70">
                    <x-ui.td class="tabular font-semibold text-slate-900">{{ $inward->grn_no }}</x-ui.td>
                    <x-ui.td class="text-sm">{{ $inward->supplier_name }}</x-ui.td>
                    <x-ui.td class="text-sm">{{ $inward->supplier_invoice_no ?? '—' }}@if ($inward->supplier_invoice_date)<span class="block text-xs text-slate-500">{{ $inward->supplier_invoice_date->format('d M Y') }}</span>@endif</x-ui.td>
                    <x-ui.td class="text-sm">{{ $inward->location->name }}</x-ui.td>
                    <x-ui.td align="right" class="tabular">{{ $inward->units_count }}</x-ui.td>
                    <x-ui.td class="whitespace-nowrap text-sm">{{ $inward->received_on->format('d M Y') }} <span class="block text-xs text-slate-500">{{ $inward->creator?->name }}</span></x-ui.td>
                </tr>
            @empty
                <x-ui.empty-row :colspan="6" :title="__('No goods receipts yet')" icon="cube" />
            @endforelse
        </x-ui.table>
    @elseif ($tab === 'locations')
        <div class="mb-4 flex justify-end"><x-ui.button icon="plus" wire:click="editLocation">{{ __('New location') }}</x-ui.button></div>
        <x-ui.table>
            <x-slot:head>
                <x-ui.th>{{ __('Location') }}</x-ui.th>
                <x-ui.th>{{ __('Branch') }}</x-ui.th>
                <x-ui.th>{{ __('Type') }}</x-ui.th>
                <x-ui.th align="right">{{ __('Units') }}</x-ui.th>
                <x-ui.th>{{ __('Status') }}</x-ui.th>
                <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
            </x-slot:head>
            @forelse ($locationList as $item)
                <tr wire:key="loc-{{ $item->id }}">
                    <x-ui.td><p class="font-medium text-slate-900">{{ $item->name }}</p><p class="font-mono text-xs text-slate-400">{{ $item->code }}</p></x-ui.td>
                    <x-ui.td class="text-sm">{{ $item->branch->name }}</x-ui.td>
                    <x-ui.td class="text-sm">{{ App\Models\StockLocation::TYPES[$item->type] ?? $item->type }}</x-ui.td>
                    <x-ui.td align="right" class="tabular">{{ $item->units_count }}</x-ui.td>
                    <x-ui.td><x-ui.active-badge :active="$item->is_active" /></x-ui.td>
                    <x-ui.td align="right"><x-ui.button size="sm" variant="ghost" icon="pencil" wire:click="editLocation({{ $item->id }})">{{ __('Edit') }}</x-ui.button></x-ui.td>
                </tr>
            @empty
                <x-ui.empty-row :colspan="6" :title="__('No locations yet')" icon="map" />
            @endforelse
        </x-ui.table>
    @else
        <x-ui.table :paginator="$units">
            <x-slot:toolbar>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Chassis or engine number…')" />
                    <select wire:model.live="product" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Product') }}">
                        <option value="">{{ __('Any product') }}</option>
                        @foreach ($products as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    <select wire:model.live="location" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Location') }}">
                        <option value="">{{ __('Any location') }}</option>
                        @foreach ($locations as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    <select wire:model.live="status" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Status') }}">
                        <option value="">{{ __('Any status') }}</option>
                        @foreach (UnitStatus::cases() as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach
                    </select>
                </div>
            </x-slot:toolbar>
            <x-slot:head>
                <x-ui.th sortable="chassis_no" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Chassis / engine') }}</x-ui.th>
                <x-ui.th>{{ __('Product') }}</x-ui.th>
                <x-ui.th>{{ __('Location') }}</x-ui.th>
                <x-ui.th sortable="received_on" :sort-by="$sortBy" :sort-direction="$sortDirection">{{ __('Received') }}</x-ui.th>
                <x-ui.th>{{ __('Order') }}</x-ui.th>
                <x-ui.th>{{ __('Status') }}</x-ui.th>
            </x-slot:head>
            @forelse ($units as $unit)
                <tr wire:key="u-{{ $unit->id }}" class="hover:bg-slate-50/70">
                    <x-ui.td>
                        <a href="{{ route('fulfilment.inventory.units.show', $unit) }}" wire:navigate class="tabular font-semibold text-slate-900 hover:text-brand-700">{{ $unit->chassis_no }}</a>
                        <p class="tabular text-xs text-slate-500">{{ $unit->engine_no }}</p>
                    </x-ui.td>
                    <x-ui.td class="text-sm">{{ $unit->product->displayName() }}<span class="block text-xs text-slate-500">{{ collect([$unit->colour, $unit->model_year])->filter()->implode(' · ') }}</span></x-ui.td>
                    <x-ui.td class="text-sm">{{ $unit->location->name }}</x-ui.td>
                    <x-ui.td class="whitespace-nowrap text-sm">{{ $unit->received_on->format('d M Y') }}<span class="block text-xs text-slate-500">{{ trans_choice(':count day in stock|:count days in stock', (int) $unit->received_on->diffInDays(today()), ['count' => (int) $unit->received_on->diffInDays(today())]) }}</span></x-ui.td>
                    <x-ui.td class="tabular text-sm">
                        @if ($unit->activeAllocation)<a href="{{ route('sales.orders.show', $unit->activeAllocation->order_id) }}" wire:navigate class="text-brand-700 hover:underline">{{ $unit->activeAllocation->order->order_no }}</a>@else<span class="text-slate-400">—</span>@endif
                    </x-ui.td>
                    <x-ui.td><x-ui.badge :tone="$unit->status->tone()">{{ $unit->status->label() }}</x-ui.badge></x-ui.td>
                </tr>
            @empty
                <x-ui.empty-row :colspan="6" :title="__('No units match')" icon="cube" />
            @endforelse
        </x-ui.table>
    @endif

    @if ($modal === 'grn')
        <x-ui.drawer wire:model="modal" width="max-w-4xl" :title="__('Receive stock (GRN)')" :description="__('One row per physical unit. Chassis and engine numbers must be unique across all stock.')">
            <form id="grn-form" wire:submit="saveGrn" class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.select :label="__('Location')" wire:model="form.stock_location_id" name="form.stock_location_id" :options="$locations" :placeholder="__('Choose…')" required />
                    <x-ui.input :label="__('Supplier')" wire:model="form.supplier_name" name="form.supplier_name" required />
                    <x-ui.input type="date" :label="__('Received on')" wire:model="form.received_on" name="form.received_on" required />
                    <x-ui.input :label="__('Supplier invoice no.')" wire:model="form.supplier_invoice_no" name="form.supplier_invoice_no" />
                    <x-ui.input type="date" :label="__('Invoice date')" wire:model="form.supplier_invoice_date" name="form.supplier_invoice_date" />
                    <x-ui.input :label="__('Remarks')" wire:model="form.remarks" name="form.remarks" />
                </div>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr>
                            <th class="px-2 py-2 text-left font-medium">{{ __('Product') }}</th>
                            <th class="px-2 py-2 text-left font-medium">{{ __('Chassis no.') }}</th>
                            <th class="px-2 py-2 text-left font-medium">{{ __('Engine no.') }}</th>
                            <th class="px-2 py-2 text-left font-medium">{{ __('Colour') }}</th>
                            <th class="px-2 py-2 text-left font-medium">{{ __('Year') }}</th>
                            <th class="px-2 py-2 text-left font-medium">{{ __('Cost (₹)') }}</th>
                            <th></th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($rows as $index => $row)
                                <tr wire:key="row-{{ $index }}">
                                    <td class="px-2 py-1.5">
                                        <select wire:model="rows.{{ $index }}.product_id" class="form-control h-9 py-1" aria-label="{{ __('Product') }}">
                                            <option value="">{{ __('Choose…') }}</option>
                                            @foreach ($products as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                        </select>
                                        @error("rows.{$index}.product_id")<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </td>
                                    <td class="px-2 py-1.5"><input wire:model="rows.{{ $index }}.chassis_no" class="form-control h-9 uppercase" aria-label="{{ __('Chassis no.') }}">@error("rows.{$index}.chassis_no")<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</td>
                                    <td class="px-2 py-1.5"><input wire:model="rows.{{ $index }}.engine_no" class="form-control h-9 uppercase" aria-label="{{ __('Engine no.') }}">@error("rows.{$index}.engine_no")<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</td>
                                    <td class="px-2 py-1.5"><input wire:model="rows.{{ $index }}.colour" class="form-control h-9 w-24" aria-label="{{ __('Colour') }}"></td>
                                    <td class="px-2 py-1.5"><input wire:model="rows.{{ $index }}.model_year" inputmode="numeric" class="form-control h-9 w-20" aria-label="{{ __('Model year') }}"></td>
                                    <td class="px-2 py-1.5"><input wire:model="rows.{{ $index }}.purchase_cost" inputmode="decimal" class="form-control h-9 w-28" aria-label="{{ __('Purchase cost') }}"></td>
                                    <td class="px-2 py-1.5"><button type="button" wire:click="removeRow({{ $index }})" class="text-slate-400 hover:text-rose-600" aria-label="{{ __('Remove row') }}"><x-ui.icon name="x" class="size-4" /></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-ui.button variant="secondary" size="sm" icon="plus" wire:click="addRow">{{ __('Add unit') }}</x-ui.button>
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="grn-form" wire:target="saveGrn">{{ __('Save GRN') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.drawer>
    @elseif ($modal === 'allocate' && $allocateOrder)
        <x-ui.modal wire:model="modal" width="max-w-2xl" :title="__('Allocate a unit to :order', ['order' => $allocateOrder->order_no])" :description="__('Available units of the ordered product in this branch, oldest stock first.')">
            <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-200">
                @forelse ($candidates as $unit)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div>
                            <p class="tabular text-sm font-medium text-slate-900">{{ $unit->chassis_no }} <span class="text-xs font-normal text-slate-500">· {{ $unit->engine_no }}</span></p>
                            <p class="text-xs text-slate-500">{{ $unit->product->displayName() }} · {{ collect([$unit->colour, $unit->model_year, $unit->location->name])->filter()->implode(' · ') }}</p>
                        </div>
                        <x-ui.button size="sm" wire:click="allocate({{ $unit->id }})" wire:target="allocate({{ $unit->id }})">{{ __('Allocate') }}</x-ui.button>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-sm text-slate-500">{{ __('No matching unit in stock. Receive stock or transfer one from another branch.') }}</li>
                @endforelse
            </ul>
            <x-slot:footer><x-ui.button variant="secondary" x-on:click="open = false">{{ __('Close') }}</x-ui.button></x-slot:footer>
        </x-ui.modal>
    @elseif ($modal === 'location')
        <x-ui.modal wire:model="modal" :title="$targetId ? __('Edit location') : __('New location')">
            <form id="loc-form" wire:submit="saveLocation" class="space-y-4">
                <x-ui.select :label="__('Branch')" wire:model="form.branch_id" name="form.branch_id" :options="auth()->user()->accessibleBranches()->pluck('name', 'id')" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input :label="__('Code')" wire:model="form.code" name="form.code" class="font-mono uppercase" required />
                    <x-ui.input :label="__('Name')" wire:model="form.name" name="form.name" required />
                </div>
                <x-ui.select :label="__('Type')" wire:model="form.type" name="form.type" :options="App\Models\StockLocation::TYPES" />
                <x-ui.checkbox :label="__('Active')" wire:model="form.is_active" />
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="loc-form" wire:target="saveLocation">{{ __('Save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
