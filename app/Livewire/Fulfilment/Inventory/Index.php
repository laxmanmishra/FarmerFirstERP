<?php

namespace App\Livewire\Fulfilment\Inventory;

use App\Actions\Inventory\AllocationFlow;
use App\Actions\Inventory\ReceiveStock;
use App\Enums\LineType;
use App\Enums\RequirementState;
use App\Enums\UnitStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\FulfilmentTaskType;
use App\Models\InventoryUnit;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockInward;
use App\Models\StockLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Inventory (SRS §53–56): physical stock by chassis, orders awaiting a unit, goods receipts
 * and stock locations.
 */
class Index extends Component
{
    use InteractsWithUi, WithDataTable;

    /** @var list<string> */
    protected array $sortable = ['chassis_no', 'received_on'];

    #[Url(except: 'stock')]
    public string $tab = 'stock';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $product = '';

    #[Url(except: '')]
    public string $location = '';

    /** 'grn' | 'allocate' | 'location'; false/null when closed. */
    public mixed $modal = null;

    #[Locked]
    public ?int $targetId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    public function mount(): void
    {
        $this->authorize('inventory.view');
        $this->normaliseTab();
    }

    public function updated(string $property): void
    {
        if ($property === 'tab') {
            $this->normaliseTab();
        }

        if (in_array($property, ['tab', 'status', 'product', 'location', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function openGrn(): void
    {
        $this->authorize('inventory.inward');
        $this->resetValidation();
        $this->form = ['stock_location_id' => (string) ($this->locations()->keys()->first() ?? ''), 'supplier_name' => '', 'supplier_invoice_no' => '',
            'supplier_invoice_date' => '', 'received_on' => today()->toDateString(), 'remarks' => ''];
        $this->rows = [$this->blankRow()];
        $this->modal = 'grn';
    }

    public function addRow(): void
    {
        $last = end($this->rows) ?: $this->blankRow();
        $this->rows[] = [...$this->blankRow(), 'product_id' => $last['product_id'], 'colour' => $last['colour'], 'model_year' => $last['model_year']];
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows) ?: [$this->blankRow()];
    }

    public function saveGrn(ReceiveStock $receive): void
    {
        $this->authorize('inventory.inward');

        $this->validate([
            'form.stock_location_id' => ['required', Rule::in($this->locations()->keys()->map(fn (int $id) => (string) $id)->all())],
            'form.supplier_name' => ['required', 'string', 'max:150'],
            'form.supplier_invoice_no' => ['nullable', 'string', 'max:60'],
            'form.supplier_invoice_date' => ['nullable', 'date', 'before_or_equal:today'],
            'form.received_on' => ['required', 'date', 'before_or_equal:today'],
            'form.remarks' => ['nullable', 'string', 'max:1000'],
            'rows' => ['required', 'array', 'min:1', 'max:50'],
            'rows.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'rows.*.chassis_no' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'rows.*.engine_no' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'rows.*.colour' => ['nullable', 'string', 'max:40'],
            'rows.*.model_year' => ['nullable', 'integer', 'between:2000,2100'],
            'rows.*.purchase_cost' => ['nullable', 'numeric', 'min:0'],
        ], attributes: ['rows.*.chassis_no' => __('chassis number'), 'rows.*.engine_no' => __('engine number'), 'rows.*.product_id' => __('product'), 'form.supplier_name' => __('supplier')]);

        $units = array_map(fn (array $row) => [
            'product_id' => (int) $row['product_id'],
            'product_variant_id' => null,
            'chassis_no' => $row['chassis_no'],
            'engine_no' => $row['engine_no'],
            'colour' => $row['colour'] ?: null,
            'model_year' => $row['model_year'] !== '' ? (int) $row['model_year'] : null,
            'purchase_cost' => $row['purchase_cost'] !== '' ? $row['purchase_cost'] : null,
        ], $this->rows);

        $header = collect($this->form)->except('stock_location_id')->map(fn ($value) => $value === '' ? null : $value)->all();
        $location = StockLocation::query()->findOrFail($this->form['stock_location_id']);

        if ($inward = $this->attempt(fn () => $receive->handle(Auth::user(), $location, $header, $units))) {
            $this->modal = null;
            $this->toast(__(':grn saved: :count units received.', ['grn' => $inward->grn_no, 'count' => count($units)]));
        }
    }

    public function openAllocate(int $orderId): void
    {
        $this->authorize('inventory.allocate');
        $this->targetId = Order::query()->visibleTo(Auth::user())->findOrFail($orderId)->id;
        $this->modal = 'allocate';
    }

    public function allocate(int $unitId, AllocationFlow $flow): void
    {
        $this->authorize('inventory.allocate');
        $order = Order::query()->visibleTo(Auth::user())->findOrFail($this->targetId);
        $unit = InventoryUnit::query()->visibleTo(Auth::user())->findOrFail($unitId);

        if ($this->attempt(fn () => $flow->allocate(Auth::user(), $order, $unit))) {
            $this->modal = null;
            $this->toast(__(':chassis allocated to :order.', ['chassis' => $unit->chassis_no, 'order' => $order->order_no]));
        }
    }

    public function editLocation(?int $id = null): void
    {
        $this->authorize('inventory.configure');
        $this->resetValidation();
        $this->targetId = $id;
        $this->form = $id ? StockLocation::query()->findOrFail($id)->only(['branch_id', 'code', 'name', 'type', 'is_active'])
            : ['branch_id' => Auth::user()->workingBranch()?->id, 'code' => '', 'name' => '', 'type' => 'yard', 'is_active' => true];
        $this->modal = 'location';
    }

    public function saveLocation(): void
    {
        $this->authorize('inventory.configure');
        $record = $this->targetId ? StockLocation::query()->findOrFail($this->targetId) : new StockLocation;

        $validated = $this->validate([
            'form.branch_id' => ['required', Rule::in(Auth::user()->accessibleBranches()->pluck('id')->all())],
            'form.code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_\-]+$/', Rule::unique('stock_locations', 'code')->ignore($record->id)],
            'form.name' => ['required', 'string', 'max:100'],
            'form.type' => ['required', Rule::in(array_keys(StockLocation::TYPES))],
            'form.is_active' => ['boolean'],
        ], attributes: ['form.branch_id' => __('branch')])['form'];

        $record->fill($validated)->save();
        $this->modal = null;
        $this->toast(__('Location saved.'));
    }

    public function render(): mixed
    {
        $user = Auth::user();

        $data = match ($this->tab) {
            'allocation' => ['orders' => $this->awaitingAllocation()->paginate($this->perPage)],
            'grn' => ['inwards' => StockInward::query()->whereIn('branch_id', $user->accessibleBranches()->pluck('id'))
                ->with(['location:id,name', 'creator:id,name'])->withCount('units')->latest('id')->paginate($this->perPage)],
            'locations' => ['locationList' => StockLocation::query()->whereIn('branch_id', $user->accessibleBranches()->pluck('id'))->with('branch:id,name')
                ->withCount('units')->orderBy('name')->get()],
            default => ['units' => $this->applySorting(InventoryUnit::query()->visibleTo($user)
                ->with(['product.brand', 'location:id,name', 'activeAllocation.order:id,order_no'])
                ->when(UnitStatus::tryFrom($this->status), fn (Builder $query, UnitStatus $status) => $query->where('status', $status))
                ->when(ctype_digit($this->product), fn (Builder $query) => $query->where('product_id', $this->product))
                ->when(ctype_digit($this->location), fn (Builder $query) => $query->where('stock_location_id', $this->location))
                ->when($this->searchTerm(), fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query->where('chassis_no', 'like', $term)->orWhere('engine_no', 'like', $term))), 'id')
                ->paginate($this->perPage)],
        };

        $order = $this->modal === 'allocate' ? Order::query()->with(['items', 'customer:id,name'])->find($this->targetId) : null;

        return view('livewire.fulfilment.inventory.index', $data + [
            'tabs' => $this->tabs(),
            'products' => Product::query()->with('brand')->where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn (Product $product) => [$product->id => $product->displayName()]),
            'locations' => $this->locations(),
            'counts' => InventoryUnit::query()->visibleTo($user)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'allocateOrder' => $order,
            'candidates' => $order ? $this->candidates($order) : collect(),
        ])->title(__('Inventory'));
    }

    /**
     * Open orders whose unit-allocation task is needed and not yet complete.
     *
     * @return Builder<Order>
     */
    private function awaitingAllocation(): Builder
    {
        return Order::query()->visibleTo(Auth::user())->whereNull('cancelled_at')
            ->whereHas('fulfilment.tasks', fn (Builder $query) => $query
                ->whereIn('requirement_state', [RequirementState::Required, RequirementState::Conditional, RequirementState::Waived])
                ->whereHas('type', fn (Builder $query) => $query->where('driven_by', FulfilmentTaskType::DRIVEN_BY_ALLOCATION))
                ->whereHas('stage', fn (Builder $query) => $query->where('is_completion', false)->where('is_final', false)))
            ->with(['customer:id,name,mobile', 'items.product.brand', 'primarySalesman:id,name'])
            ->withCount('activeAllocations')
            ->orderByRaw('expected_delivery_date is null')
            ->orderBy('expected_delivery_date');
    }

    /**
     * @return Collection<int, InventoryUnit>
     */
    private function candidates(Order $order): Collection
    {
        $productIds = $order->items->where('line_type', LineType::Product)->pluck('product_id')->filter()->unique();

        return InventoryUnit::query()->with(['product.brand', 'location:id,name'])
            ->where('status', UnitStatus::Available)->where('branch_id', $order->branch_id)->whereIn('product_id', $productIds)
            ->oldest('received_on')->limit(50)->get();
    }

    /**
     * @return Collection<int, string>
     */
    private function locations(): Collection
    {
        return StockLocation::query()->active()->whereIn('branch_id', Auth::user()->accessibleBranches()->pluck('id'))->orderBy('name')->pluck('name', 'id');
    }

    /**
     * @return array<string, mixed>
     */
    private function blankRow(): array
    {
        return ['product_id' => '', 'chassis_no' => '', 'engine_no' => '', 'colour' => '', 'model_year' => (string) now()->year, 'purchase_cost' => ''];
    }

    /**
     * @return array<string, string>
     */
    private function tabs(): array
    {
        return array_filter([
            'stock' => __('Stock'),
            'allocation' => __('Awaiting allocation'),
            'grn' => __('Goods receipts'),
            'locations' => Auth::user()->can('inventory.configure') ? __('Locations') : null,
        ]);
    }

    private function normaliseTab(): void
    {
        $this->tab = array_key_exists($this->tab, $this->tabs()) ? $this->tab : 'stock';
    }
}
