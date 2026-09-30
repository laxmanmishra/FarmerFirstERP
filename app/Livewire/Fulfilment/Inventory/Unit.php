<?php

namespace App\Livewire\Fulfilment\Inventory;

use App\Actions\Inventory\AllocationFlow;
use App\Actions\Inventory\UnitFlow;
use App\Enums\UnitStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\InventoryUnit;
use App\Models\StockLocation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One physical unit: identity, current allocation, full allocation history and stock ledger.
 */
class Unit extends Component
{
    use InteractsWithUi;

    #[Locked]
    public int $unitId;

    /** 'transfer' | 'block' | 'unblock' | 'release' | 'reallocate'; false/null when closed. */
    public mixed $modal = null;

    /** @var array<string, string> */
    public array $form = [];

    public function mount(InventoryUnit $unit): void
    {
        $this->authorize('inventory.view');
        abort_unless(InventoryUnit::query()->visibleTo(Auth::user())->whereKey($unit->id)->exists(), 404);
        $this->unitId = $unit->id;
    }

    public function open(string $modal): void
    {
        abort_unless(in_array($modal, ['transfer', 'block', 'unblock', 'release', 'reallocate'], true), 404);
        $this->resetValidation();
        $this->form = ['location_id' => '', 'reason' => '', 'unit_id' => ''];
        $this->modal = $modal;
    }

    public function save(UnitFlow $units, AllocationFlow $allocations): void
    {
        $unit = InventoryUnit::query()->with('activeAllocation')->findOrFail($this->unitId);
        $user = Auth::user();

        $this->validate(match ($this->modal) {
            'transfer' => ['form.location_id' => ['required', 'integer', 'exists:stock_locations,id'], 'form.reason' => ['nullable', 'string', 'max:1000']],
            'reallocate' => ['form.unit_id' => ['required', 'integer', 'exists:inventory_units,id'], 'form.reason' => ['required', 'string', 'max:1000']],
            'unblock' => ['form.reason' => ['nullable', 'string', 'max:1000']],
            default => ['form.reason' => ['required', 'string', 'max:1000']],
        }, attributes: ['form.location_id' => __('location'), 'form.unit_id' => __('unit'), 'form.reason' => __('reason')]);

        $reason = $this->form['reason'];

        $done = $this->attempt(fn () => match ($this->modal) {
            'transfer' => $units->transfer($user, $unit, StockLocation::query()->whereIn('branch_id', $user->accessibleBranches()->pluck('id'))->findOrFail($this->form['location_id']), $reason ?: null),
            'block' => $units->block($user, $unit, $reason),
            'unblock' => $units->unblock($user, $unit, $reason ?: null),
            'release' => $allocations->release($user, $unit->activeAllocation ?? abort(404), $reason),
            'reallocate' => $allocations->reallocate($user, $unit->activeAllocation ?? abort(404), InventoryUnit::query()->visibleTo($user)->findOrFail($this->form['unit_id']), $reason),
            default => abort(404),
        });

        if ($done) {
            $this->modal = null;
            $this->toast(__('Stock updated.'));
        }
    }

    public function render(): mixed
    {
        $unit = InventoryUnit::query()->with([
            'product.brand', 'variant', 'location.branch', 'inward', 'activeAllocation.order.customer:id,name',
            'allocations' => fn ($query) => $query->with(['order:id,order_no', 'allocator:id,name', 'releaser:id,name']),
            'movements' => fn ($query) => $query->with(['fromLocation:id,name', 'toLocation:id,name', 'order:id,order_no', 'user:id,name']),
        ])->findOrFail($this->unitId);

        return view('livewire.fulfilment.inventory.unit', [
            'unit' => $unit,
            'locations' => $this->modal === 'transfer'
                ? StockLocation::query()->active()->whereIn('branch_id', Auth::user()->accessibleBranches()->pluck('id'))->whereKeyNot($unit->stock_location_id)->with('branch:id,name')->orderBy('name')->get()
                    ->mapWithKeys(fn (StockLocation $location) => [$location->id => $location->name.' · '.$location->branch->name])
                : collect(),
            'swapUnits' => $this->modal === 'reallocate'
                ? InventoryUnit::query()->where('status', UnitStatus::Available)->where('branch_id', $unit->branch_id)->where('product_id', $unit->product_id)->orderBy('chassis_no')
                    ->get()->mapWithKeys(fn (InventoryUnit $other) => [$other->id => $other->chassis_no.' · '.($other->colour ?? '—')])
                : collect(),
        ])->title($unit->chassis_no);
    }
}
