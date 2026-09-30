@php use App\Support\Money; @endphp
<div>
    <x-ui.page-header :title="$quotationId ? __('Edit quotation') : __('New quotation')"
        :description="$enquiry->enquiry_no.' · '.$enquiry->farmer->name.' · '.$enquiry->farmer->village->name"
        :breadcrumbs="[__('Sales') => null, __('Quotations') => route('sales.quotations.index'), ($quotationId ? __('Edit') : __('New')) => null]" />

    <form wire:submit="save" class="grid gap-6 xl:grid-cols-3" novalidate>
        <div class="space-y-6 xl:col-span-2">
            <x-ui.card :title="__('Lines')" :padding="false">
                <x-slot:actions>
                    <x-ui.button variant="ghost" size="sm" icon="plus" wire:click="addLine('product')">{{ __('Product') }}</x-ui.button>
                    <x-ui.button variant="ghost" size="sm" icon="plus" wire:click="addLine('accessory')">{{ __('Accessory') }}</x-ui.button>
                    <x-ui.button variant="ghost" size="sm" icon="plus" wire:click="addLine('charge')">{{ __('Charge') }}</x-ui.button>
                </x-slot:actions>
                <div class="divide-y divide-slate-100">
                    @error('lines')<p class="px-5 pt-3 text-xs text-rose-600">{{ $message }}</p>@enderror
                    @foreach ($lines as $index => $line)
                        <div wire:key="line-{{ $index }}" class="grid gap-3 px-5 py-4 sm:grid-cols-12">
                            <div class="sm:col-span-2">
                                <x-ui.select :label="__('Type')" wire:model.live="lines.{{ $index }}.line_type" name="lines.{{ $index }}.line_type" :options="$lineTypes" />
                            </div>
                            @if ($line['line_type'] !== 'charge')
                                <div class="sm:col-span-4">
                                    <x-ui.select :label="__('Product')" wire:model.live="lines.{{ $index }}.product_id" name="lines.{{ $index }}.product_id" :placeholder="__('Free text')"
                                        :options="$products->mapWithKeys(fn ($p) => [$p->id => $p->brand->name.' '.$p->name])" />
                                </div>
                                <div class="sm:col-span-3">
                                    <x-ui.select :label="__('Variant')" wire:model.live="lines.{{ $index }}.product_variant_id" name="lines.{{ $index }}.product_variant_id" :placeholder="__('—')"
                                        :disabled="! $line['product_id']" :options="$variants->where('product_id', (int) $line['product_id'])->pluck('name', 'id')" />
                                </div>
                                <div class="sm:col-span-3"><x-ui.input :label="__('Description')" wire:model="lines.{{ $index }}.description" name="lines.{{ $index }}.description" required /></div>
                            @else
                                <div class="sm:col-span-10"><x-ui.input :label="__('Charge')" wire:model="lines.{{ $index }}.description" name="lines.{{ $index }}.description" :placeholder="__('e.g. RTO registration, insurance, handling')" required /></div>
                            @endif
                            <div class="sm:col-span-2"><x-ui.input type="number" :label="__('Qty')" wire:model.live.debounce.400ms="lines.{{ $index }}.quantity" name="lines.{{ $index }}.quantity" min="1" /></div>
                            <div class="sm:col-span-3"><x-ui.input :label="__('Unit price (₹)')" wire:model.live.debounce.400ms="lines.{{ $index }}.unit_price" name="lines.{{ $index }}.unit_price" inputmode="decimal" /></div>
                            <div class="sm:col-span-3"><x-ui.input :label="__('Discount (₹)')" wire:model.live.debounce.400ms="lines.{{ $index }}.discount_amount" name="lines.{{ $index }}.discount_amount" inputmode="decimal" /></div>
                            <div class="sm:col-span-2"><x-ui.input :label="__('Tax %')" wire:model.live.debounce.400ms="lines.{{ $index }}.tax_percent" name="lines.{{ $index }}.tax_percent" inputmode="decimal" /></div>
                            <div class="flex items-end justify-end sm:col-span-2">
                                @if (count($lines) > 1)
                                    <x-ui.button variant="danger-ghost" size="xs" wire:click="removeLine({{ $index }})">{{ __('Remove') }}</x-ui.button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Terms')">
                <div class="space-y-4">
                    <x-ui.textarea :label="__('Terms & conditions')" wire:model="terms" name="terms" rows="4" />
                    <x-ui.textarea :label="__('Internal remarks')" wire:model="remarks" name="remarks" rows="2" :hint="__('Not printed on the quotation.')" />
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card :title="__('Summary')">
                <div class="space-y-4">
                    <x-ui.input type="date" :label="__('Valid until')" wire:model="valid_until" name="valid_until" min="{{ today()->toDateString() }}" required />
                    @if ($enquiry->exchangeTractor)
                        <x-ui.input :label="__('Exchange value offered (₹)')" wire:model.live.debounce.400ms="exchange_value" name="exchange_value" inputmode="decimal"
                            :hint="__(':tractor — customer expects :price', ['tractor' => $enquiry->exchangeTractor->brand_name.' '.$enquiry->exchangeTractor->model_name, 'price' => $enquiry->exchangeTractor->customer_expected_price ? Money::format($enquiry->exchangeTractor->customer_expected_price) : '—'])" />
                    @endif
                    <x-ui.input :label="__('Finance amount (₹)')" wire:model.live.debounce.400ms="finance_amount" name="finance_amount" inputmode="decimal" :hint="__('Leave 0 for a cash deal.')" />
                </div>

                <dl class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-sm">
                    @if ($totals)
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Gross') }}</dt><dd class="tabular">{{ Money::format($totals['gross_total']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Discount') }} ({{ $totals['discount_percent'] }}%)</dt><dd class="tabular text-rose-600">−{{ Money::format($totals['discount_total']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Tax') }}</dt><dd class="tabular">{{ Money::format($totals['tax_total']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Charges') }}</dt><dd class="tabular">{{ Money::format($totals['charges_total']) }}</dd></div>
                        @if (Money::compare($totals['exchange_value'], 0) > 0)
                            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Exchange') }}</dt><dd class="tabular text-rose-600">−{{ Money::format($totals['exchange_value']) }}</dd></div>
                        @endif
                        <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-semibold"><dt>{{ __('Net amount') }}</dt><dd class="tabular">{{ Money::format($totals['net_amount']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Finance') }}</dt><dd class="tabular">{{ Money::format($totals['finance_amount']) }}</dd></div>
                        <div class="flex justify-between font-medium"><dt>{{ __('Customer contribution') }}</dt><dd class="tabular">{{ Money::format($totals['customer_contribution']) }}</dd></div>
                    @else
                        <p class="text-rose-600">{{ $calcError }}</p>
                    @endif
                </dl>

                @if (! $withinLimit)
                    <x-ui.alert tone="warning" class="mt-4">{{ __('This discount is above your limit. Saving sends it for approval before it can be issued.') }}</x-ui.alert>
                @endif
            </x-ui.card>

            <div class="flex flex-col gap-2">
                <x-ui.button type="submit" size="lg" class="w-full" wire:target="save">{{ __('Save quotation') }}</x-ui.button>
                <x-ui.button variant="secondary" size="lg" class="w-full" :href="$quotationId ? route('sales.quotations.show', $quotationId) : route('crm.enquiries.show', $enquiryId)" wire:navigate>{{ __('Cancel') }}</x-ui.button>
            </div>
        </div>
    </form>
</div>
