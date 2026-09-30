<?php

namespace App\Livewire\Admin\Products;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Price master with effective dates (SRS v6.1 §4). A new price for the same
 * product/variant/branch ends the previous open-ended price the day before.
 */
class Prices extends Component
{
    use InteractsWithUi, WithPagination;

    public string $productFilter = '';

    public bool $currentOnly = true;

    public bool $showForm = false;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->authorize('products.view');
    }

    public function create(): void
    {
        $this->authorize('products.manage');
        $this->resetValidation();
        $this->form = ['product_id' => $this->productFilter, 'product_variant_id' => '', 'branch_id' => '', 'price' => '', 'tax_percent' => '12', 'effective_from' => today()->toDateString()];
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('products.manage');

        $validated = $this->validate([
            'form.product_id' => ['required', Rule::exists('products', 'id')],
            'form.product_variant_id' => ['nullable', Rule::exists('product_variants', 'id')->where('product_id', $this->form['product_id'] ?? 0)],
            'form.branch_id' => ['nullable', Rule::exists('branches', 'id')],
            'form.price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'form.tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'form.effective_from' => ['required', 'date'],
        ], attributes: ['form.product_id' => __('model'), 'form.price' => __('price'), 'form.tax_percent' => __('tax'), 'form.effective_from' => __('effective date')])['form'];

        $attributes = array_map(fn ($value) => $value === '' ? null : $value, $validated);

        $clash = ProductPrice::query()->active()
            ->where('product_id', $attributes['product_id'])
            ->where('product_variant_id', $attributes['product_variant_id'])
            ->where('branch_id', $attributes['branch_id'])
            ->where('effective_from', $attributes['effective_from'])
            ->exists();

        if ($clash) {
            $this->addError('form.effective_from', __('A price for this model already starts on this date. Deactivate it first.'));

            return;
        }

        ProductPrice::query()->active()
            ->where('product_id', $attributes['product_id'])
            ->where(fn ($query) => $attributes['product_variant_id'] ? $query->where('product_variant_id', $attributes['product_variant_id']) : $query->whereNull('product_variant_id'))
            ->where(fn ($query) => $attributes['branch_id'] ? $query->where('branch_id', $attributes['branch_id']) : $query->whereNull('branch_id'))
            ->where('effective_from', '<', $attributes['effective_from'])
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $attributes['effective_from']))
            ->get()
            ->each(fn (ProductPrice $previous) => $previous->update(['effective_to' => CarbonImmutable::parse($attributes['effective_from'])->subDay()]));

        ProductPrice::create($attributes);
        $this->showForm = false;
        $this->toast(__('Price saved.'));
    }

    public function toggle(int $id): void
    {
        $this->authorize('products.manage');
        $price = ProductPrice::query()->findOrFail($id);
        $price->update(['is_active' => ! $price->is_active]);
    }

    public function render(): mixed
    {
        $today = today()->toDateString();

        return view('livewire.admin.products.prices', [
            'prices' => ProductPrice::query()->with(['product.brand', 'variant', 'branch'])
                ->when($this->productFilter !== '', fn ($query) => $query->where('product_id', $this->productFilter))
                ->when($this->currentOnly, fn ($query) => $query->active()->where('effective_from', '<=', $today)
                    ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today)))
                ->orderBy('product_id')->orderByDesc('effective_from')
                ->paginate(25),
            'products' => Product::query()->with('brand:id,name')->orderBy('name')->get()->mapWithKeys(fn (Product $product) => [$product->id => $product->brand->name.' '.$product->name]),
            'variants' => ($this->form['product_id'] ?? '') !== '' ? ProductVariant::query()->where('product_id', $this->form['product_id'])->orderBy('name')->pluck('name', 'id') : collect(),
            'branches' => Branch::query()->active()->orderBy('name')->pluck('name', 'id'),
            'canManage' => auth()->user()->can('products.manage'),
        ]);
    }
}
