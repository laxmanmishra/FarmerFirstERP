<?php

namespace App\Livewire\Admin\Products;

use App\Enums\ProductType;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Product catalogue: brands, models and variants. Physical units belong to Inventory.
 */
#[Title('Products')]
class Index extends Component
{
    use InteractsWithUi, WithDataTable;

    #[Url(except: 'products')]
    public string $tab = 'products';

    #[Url(except: '')]
    public string $brandFilter = '';

    #[Url(except: '')]
    public string $typeFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->authorize('products.view');
        $this->normaliseTab();
    }

    public function updatedTab(): void
    {
        $this->normaliseTab();
        $this->showForm = false;
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('products.manage');
        $this->resetValidation();
        $this->editingId = null;
        $this->form = match ($this->tab) {
            'brands' => ['code' => '', 'name' => '', 'is_dealer_brand' => false],
            'variants' => ['product_id' => '', 'name' => '', 'code' => ''],
            default => ['brand_id' => $this->brandFilter, 'product_type' => ProductType::Tractor->value, 'name' => '', 'hp' => '', 'description' => ''],
        };
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('products.manage');
        $this->resetValidation();
        $record = $this->model()::query()->findOrFail($id);
        $this->editingId = $record->id;
        $this->form = match ($this->tab) {
            'brands' => $record->only(['code', 'name', 'is_dealer_brand']),
            'variants' => ['product_id' => $record->product_id, 'name' => $record->name, 'code' => (string) $record->code],
            default => ['brand_id' => $record->brand_id, 'product_type' => $record->product_type->value, 'name' => $record->name, 'hp' => (string) $record->hp, 'description' => (string) $record->description],
        };
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('products.manage');

        $rules = match ($this->tab) {
            'brands' => [
                'form.code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('brands', 'code')->ignore($this->editingId)],
                'form.name' => ['required', 'string', 'max:100', Rule::unique('brands', 'name')->ignore($this->editingId)],
                'form.is_dealer_brand' => ['boolean'],
            ],
            'variants' => [
                'form.product_id' => ['required', Rule::exists('products', 'id')],
                'form.name' => ['required', 'string', 'max:150', Rule::unique('product_variants', 'name')->where('product_id', $this->form['product_id'] ?? null)->ignore($this->editingId)],
                'form.code' => ['nullable', 'string', 'max:50', Rule::unique('product_variants', 'code')->ignore($this->editingId)],
            ],
            default => [
                'form.brand_id' => ['required', Rule::exists('brands', 'id')],
                'form.product_type' => ['required', Rule::enum(ProductType::class)],
                'form.name' => ['required', 'string', 'max:150', Rule::unique('products', 'name')->where('brand_id', $this->form['brand_id'] ?? null)->ignore($this->editingId)],
                'form.hp' => ['nullable', 'integer', 'min:1', 'max:500'],
                'form.description' => ['nullable', 'string', 'max:500'],
            ],
        };

        $attributes = array_map(fn ($value) => $value === '' ? null : $value, $this->validate($rules, attributes: [
            'form.code' => __('code'), 'form.name' => __('name'), 'form.brand_id' => __('brand'), 'form.product_id' => __('model'), 'form.hp' => __('HP'),
        ])['form']);

        if ($this->tab === 'brands') {
            $attributes['code'] = strtoupper($attributes['code']);
        }

        $this->editingId
            ? $this->model()::query()->findOrFail($this->editingId)->update($attributes)
            : $this->model()::create($attributes);

        $this->showForm = false;
        $this->toast(__('Saved.'));
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('products.manage');
        $record = $this->model()::query()->findOrFail($id);
        $record->update(['is_active' => ! $record->is_active]);
        $this->toast($record->is_active ? __('Activated.') : __('Deactivated. It is no longer offered for new enquiries.'));
    }

    public function render(): mixed
    {
        $term = $this->searchTerm();

        $records = match ($this->tab) {
            'brands' => Brand::query()->withCount('products')
                ->when($term, fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('code', 'like', $term))
                ->orderByDesc('is_dealer_brand')->orderBy('name'),
            'variants' => ProductVariant::query()->with('product.brand')
                ->when($this->brandFilter !== '', fn (Builder $query) => $query->whereHas('product', fn (Builder $query) => $query->where('brand_id', $this->brandFilter)))
                ->when($term, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('code', 'like', $term)))
                ->orderBy('product_id')->orderBy('name'),
            default => Product::query()->with('brand')->withCount('variants')
                ->when($this->brandFilter !== '', fn (Builder $query) => $query->where('brand_id', $this->brandFilter))
                ->when(ProductType::tryFrom($this->typeFilter), fn (Builder $query, ProductType $type) => $query->where('product_type', $type))
                ->when($term, fn (Builder $query) => $query->where('name', 'like', $term))
                ->orderBy('brand_id')->orderBy('name'),
        };

        return view('livewire.admin.products.index', [
            'records' => $records->paginate($this->perPage),
            'brands' => Brand::query()->orderBy('name')->pluck('name', 'id'),
            'productOptions' => Product::query()->with('brand:id,name')->orderBy('name')->get()->mapWithKeys(fn (Product $product) => [$product->id => $product->brand->name.' '.$product->name]),
            'types' => ProductType::options(),
        ]);
    }

    /**
     * @return class-string<Brand|Product|ProductVariant>
     */
    private function model(): string
    {
        return match ($this->tab) {
            'brands' => Brand::class,
            'variants' => ProductVariant::class,
            default => Product::class,
        };
    }

    private function normaliseTab(): void
    {
        $this->tab = in_array($this->tab, ['brands', 'products', 'variants'], true) ? $this->tab : 'products';
    }
}
