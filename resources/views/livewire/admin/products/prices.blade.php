@php use App\Support\Money; @endphp
<div>
    <x-ui.table :paginator="$prices">
        <x-slot:toolbar>
            <div class="flex flex-wrap items-center gap-3">
                <select wire:model.live="productFilter" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Model') }}">
                    <option value="">{{ __('All models') }}</option>
                    @foreach ($products as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
                <x-ui.checkbox :label="__('Current prices only')" wire:model.live="currentOnly" />
            </div>
            @if ($canManage)
                <x-ui.button size="sm" icon="plus" wire:click="create">{{ __('New price') }}</x-ui.button>
            @endif
        </x-slot:toolbar>
        <x-slot:head>
            <x-ui.th>{{ __('Model') }}</x-ui.th>
            <x-ui.th>{{ __('Variant') }}</x-ui.th>
            <x-ui.th>{{ __('Branch') }}</x-ui.th>
            <x-ui.th align="right">{{ __('Price') }}</x-ui.th>
            <x-ui.th align="right">{{ __('Tax') }}</x-ui.th>
            <x-ui.th>{{ __('Effective') }}</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
        </x-slot:head>
        @forelse ($prices as $price)
            <tr wire:key="price-{{ $price->id }}" @class(['opacity-60' => ! $price->is_active])>
                <x-ui.td class="font-medium text-slate-900">{{ $price->product->brand->name }} {{ $price->product->name }}</x-ui.td>
                <x-ui.td class="text-sm">{{ $price->variant?->name ?? __('All variants') }}</x-ui.td>
                <x-ui.td class="text-sm">{{ $price->branch?->name ?? __('All branches') }}</x-ui.td>
                <x-ui.td align="right" class="tabular font-medium">{{ Money::format($price->price) }}</x-ui.td>
                <x-ui.td align="right" class="tabular text-xs">{{ $price->tax_percent }}%</x-ui.td>
                <x-ui.td class="whitespace-nowrap text-xs text-slate-500">{{ $price->effective_from->format('d M Y') }} – {{ $price->effective_to?->format('d M Y') ?? __('open') }}</x-ui.td>
                <x-ui.td align="right">
                    @if ($canManage)<x-ui.button variant="ghost" size="xs" wire:click="toggle({{ $price->id }})">{{ $price->is_active ? __('Deactivate') : __('Activate') }}</x-ui.button>@endif
                </x-ui.td>
            </tr>
        @empty
            <x-ui.empty-row :colspan="7" :title="__('No prices')" icon="banknotes">{{ __('Quotations fill unit prices from this list.') }}</x-ui.empty-row>
        @endforelse
    </x-ui.table>

    <x-ui.drawer wire:model="showForm" :title="__('New price')" :description="__('The previous open price for the same model, variant and branch ends the day before.')">
        <form id="price-form" wire:submit="save" class="space-y-4">
            <x-ui.select :label="__('Model')" wire:model.live="form.product_id" name="form.product_id" :options="$products" :placeholder="__('Select…')" required />
            <x-ui.select :label="__('Variant')" wire:model="form.product_variant_id" name="form.product_variant_id" :options="$variants" :placeholder="__('All variants')" />
            <x-ui.select :label="__('Branch')" wire:model="form.branch_id" name="form.branch_id" :options="$branches" :placeholder="__('All branches')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input :label="__('Price (₹)')" wire:model="form.price" name="form.price" inputmode="decimal" required />
                <x-ui.input :label="__('Tax %')" wire:model="form.tax_percent" name="form.tax_percent" inputmode="decimal" required />
            </div>
            <x-ui.input type="date" :label="__('Effective from')" wire:model="form.effective_from" name="form.effective_from" required />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="price-form">{{ __('Save price') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>
</div>
