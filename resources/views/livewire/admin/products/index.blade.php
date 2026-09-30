<div>
    <x-ui.page-header :title="__('Products')" :description="__('Catalogue of tractors and implements used by enquiries, quotations and orders.')"
        :breadcrumbs="[__('Administration') => null, __('Products') => null]">
        <x-slot:actions>
            @can('products.manage')
                <x-ui.button icon="plus" wire:click="create">{{ ['brands' => __('New brand'), 'products' => __('New model'), 'variants' => __('New variant')][$tab] }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.tabs class="mb-4" :active="$tab" :tabs="['products' => __('Models'), 'variants' => __('Variants'), 'brands' => __('Brands')]" />

    <x-ui.table :paginator="$records">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" />
            @if ($tab !== 'brands')
                <div class="flex flex-wrap gap-2">
                    <select wire:model.live="brandFilter" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Brand') }}">
                        <option value="">{{ __('All brands') }}</option>
                        @foreach ($brands as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    @if ($tab === 'products')
                        <select wire:model.live="typeFilter" class="form-control h-9 w-auto py-1 pr-8" aria-label="{{ __('Type') }}">
                            <option value="">{{ __('All types') }}</option>
                            @foreach ($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    @endif
                </div>
            @endif
        </x-slot:toolbar>
        <x-slot:head>
            @if ($tab === 'brands')
                <x-ui.th>{{ __('Code') }}</x-ui.th><x-ui.th>{{ __('Brand') }}</x-ui.th><x-ui.th align="right">{{ __('Models') }}</x-ui.th>
            @elseif ($tab === 'variants')
                <x-ui.th>{{ __('Variant') }}</x-ui.th><x-ui.th>{{ __('Model') }}</x-ui.th><x-ui.th>{{ __('Code') }}</x-ui.th>
            @else
                <x-ui.th>{{ __('Model') }}</x-ui.th><x-ui.th>{{ __('Type') }}</x-ui.th><x-ui.th align="right">{{ __('HP') }}</x-ui.th><x-ui.th align="right">{{ __('Variants') }}</x-ui.th>
            @endif
            <x-ui.th>{{ __('Status') }}</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">{{ __('Actions') }}</span></x-ui.th>
        </x-slot:head>
        @forelse ($records as $record)
            <tr wire:key="{{ $tab }}-{{ $record->id }}" @class(['hover:bg-slate-50/70', 'opacity-60' => ! $record->is_active])>
                @if ($tab === 'brands')
                    <x-ui.td class="text-xs font-semibold text-slate-600">{{ $record->code }}</x-ui.td>
                    <x-ui.td class="font-medium text-slate-900">{{ $record->name }} @if ($record->is_dealer_brand)<x-ui.badge tone="brand" class="ml-1">{{ __('Dealer brand') }}</x-ui.badge>@endif</x-ui.td>
                    <x-ui.td align="right" class="tabular">{{ $record->products_count }}</x-ui.td>
                @elseif ($tab === 'variants')
                    <x-ui.td class="font-medium text-slate-900">{{ $record->name }}</x-ui.td>
                    <x-ui.td>{{ $record->product->brand->name }} {{ $record->product->name }}</x-ui.td>
                    <x-ui.td class="text-xs text-slate-500">{{ $record->code ?? '—' }}</x-ui.td>
                @else
                    <x-ui.td>
                        <p class="font-medium text-slate-900">{{ $record->name }}</p>
                        <p class="text-xs text-slate-500">{{ $record->brand->name }}</p>
                    </x-ui.td>
                    <x-ui.td><x-ui.badge>{{ $record->product_type->label() }}</x-ui.badge></x-ui.td>
                    <x-ui.td align="right" class="tabular">{{ $record->hp ?? '—' }}</x-ui.td>
                    <x-ui.td align="right" class="tabular">{{ $record->variants_count }}</x-ui.td>
                @endif
                <x-ui.td><x-ui.active-badge :active="$record->is_active" /></x-ui.td>
                <x-ui.td align="right">
                    @can('products.manage')
                        <div class="flex justify-end gap-1 whitespace-nowrap">
                            <x-ui.button variant="ghost" size="xs" icon="pencil" wire:click="edit({{ $record->id }})">{{ __('Edit') }}</x-ui.button>
                            <x-ui.button variant="ghost" size="xs" wire:click="toggleActive({{ $record->id }})">{{ $record->is_active ? __('Deactivate') : __('Activate') }}</x-ui.button>
                        </div>
                    @endcan
                </x-ui.td>
            </tr>
        @empty
            <x-ui.empty-row :colspan="6" :title="__('Nothing in the catalogue yet')" icon="cube" />
        @endforelse
    </x-ui.table>

    <x-ui.drawer wire:model="showForm" :title="($editingId ? __('Edit') : __('New')).' '.['brands' => __('brand'), 'products' => __('model'), 'variants' => __('variant')][$tab]">
        <form id="product-form" wire:submit="save" class="space-y-5">
            @if ($tab === 'brands')
                <x-ui.input :label="__('Code')" wire:model="form.code" name="form.code" required maxlength="20" />
                <x-ui.input :label="__('Name')" wire:model="form.name" name="form.name" required />
                <x-ui.checkbox :label="__('Dealer brand')" :description="__('Brands this dealership sells. Others are kept for competitor and exchange records.')" wire:model="form.is_dealer_brand" />
            @elseif ($tab === 'variants')
                <x-ui.select :label="__('Model')" wire:model="form.product_id" name="form.product_id" :options="$productOptions" :placeholder="__('Select…')" required />
                <x-ui.input :label="__('Variant name')" wire:model="form.name" name="form.name" required />
                <x-ui.input :label="__('Code')" wire:model="form.code" name="form.code" />
            @else
                <x-ui.select :label="__('Brand')" wire:model="form.brand_id" name="form.brand_id" :options="$brands" :placeholder="__('Select…')" required />
                <x-ui.select :label="__('Type')" wire:model="form.product_type" name="form.product_type" :options="$types" required />
                <x-ui.input :label="__('Model name')" wire:model="form.name" name="form.name" required />
                <x-ui.input type="number" :label="__('Horsepower')" wire:model="form.hp" name="form.hp" min="1" />
                <x-ui.textarea :label="__('Specification / notes')" wire:model="form.description" name="form.description" rows="3" />
            @endif
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="product-form" wire:target="save">{{ __('Save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>
</div>
