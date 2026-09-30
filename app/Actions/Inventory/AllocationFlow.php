<?php

namespace App\Actions\Inventory;

use App\Actions\Fulfilment\FulfilmentTaskFlow;
use App\Enums\LineType;
use App\Enums\MovementType;
use App\Enums\UnitStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\FulfilmentTask;
use App\Models\FulfilmentTaskType;
use App\Models\InventoryUnit;
use App\Models\Order;
use App\Models\StockAllocation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Allocation of physical units to orders (SRS §55). No unit can be allocated to two
 * orders at once (INV-04): the unit row is locked and the unique `active_unit_key`
 * rejects any concurrent second allocation. Reallocation keeps the full history.
 */
class AllocationFlow
{
    public function __construct(private readonly FulfilmentTaskFlow $tasks) {}

    public function allocate(User $actor, Order $order, InventoryUnit $unit): StockAllocation
    {
        $this->assertCan($actor, 'inventory.allocate');

        return DB::transaction(fn (): StockAllocation => $this->doAllocate($actor, $order, $unit));
    }

    public function release(User $actor, StockAllocation $allocation, string $reason): StockAllocation
    {
        $this->assertCan($actor, 'inventory.reallocate');

        if (trim($reason) === '') {
            throw new BusinessRuleException(__('Give the reason for releasing the unit.'), 'reason_required');
        }

        DB::transaction(function () use ($actor, $allocation, $reason): void {
            $this->doRelease($actor, $allocation, $reason);
            $this->syncTask($actor, $allocation->order()->with('items')->firstOrFail());
        });

        return $allocation;
    }

    /**
     * Swaps the unit of an allocation in one step (e.g. customer wants another colour).
     */
    public function reallocate(User $actor, StockAllocation $allocation, InventoryUnit $unit, string $reason): StockAllocation
    {
        $this->assertCan($actor, 'inventory.reallocate');

        if (trim($reason) === '') {
            throw new BusinessRuleException(__('Give the reason for reallocating.'), 'reason_required');
        }

        return DB::transaction(function () use ($actor, $allocation, $unit, $reason): StockAllocation {
            $this->doRelease($actor, $allocation, $reason);

            return $this->doAllocate($actor, $allocation->order()->firstOrFail(), $unit, $reason);
        });
    }

    /**
     * Releases every active allocation, e.g. when the order is cancelled.
     */
    public function releaseAll(User $actor, Order $order, string $reason): void
    {
        foreach ($order->activeAllocations()->get() as $allocation) {
            $this->doRelease($actor, $allocation, $reason);
        }
    }

    private function doAllocate(User $actor, Order $order, InventoryUnit $unit, ?string $remarks = null): StockAllocation
    {
        $order = Order::query()->with(['items', 'stage'])->lockForUpdate()->findOrFail($order->id);
        $unit = InventoryUnit::query()->with('product')->lockForUpdate()->findOrFail($unit->id);

        if ($order->isCancelled() || $order->stage->is_final) {
            throw new BusinessRuleException(__('The order is closed.'), 'order_closed');
        }

        if ($unit->status !== UnitStatus::Available) {
            throw new BusinessRuleException(__('Unit :chassis is :status, not available.', ['chassis' => $unit->chassis_no, 'status' => mb_strtolower($unit->status->label())]), 'unit_not_available');
        }

        if ($unit->branch_id !== $order->branch_id) {
            throw new BusinessRuleException(__('The unit is in another branch; transfer it first.'), 'unit_other_branch');
        }

        $matches = $order->items->where('line_type', LineType::Product)->contains(fn ($item) => $item->product_id === $unit->product_id
            && ($item->product_variant_id === null || $item->product_variant_id === $unit->product_variant_id));

        if (! $matches) {
            throw new BusinessRuleException(__(':product is not on this order.', ['product' => $unit->product->displayName()]), 'unit_product_mismatch');
        }

        if ($order->activeAllocations()->count() >= $order->unitsRequired()) {
            throw new BusinessRuleException(__('All units of this order are already allocated.'), 'order_fully_allocated');
        }

        try {
            $allocation = StockAllocation::create([
                'inventory_unit_id' => $unit->id,
                'order_id' => $order->id,
                'active_unit_key' => $unit->id,
                'allocated_by' => $actor->id,
                'allocated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleException(__('Unit :chassis was just allocated to another order.', ['chassis' => $unit->chassis_no]), 'unit_already_allocated');
        }

        $unit->update(['status' => UnitStatus::Allocated]);
        $unit->movements()->create([
            'type' => MovementType::Allocated,
            'from_status' => UnitStatus::Available->value,
            'to_status' => UnitStatus::Allocated->value,
            'order_id' => $order->id,
            'remarks' => $remarks,
            'user_id' => $actor->id,
        ]);

        $this->syncTask($actor, $order);

        return $allocation;
    }

    private function doRelease(User $actor, StockAllocation $allocation, string $reason): void
    {
        $allocation = StockAllocation::query()->lockForUpdate()->findOrFail($allocation->id);

        if (! $allocation->isActive()) {
            throw new BusinessRuleException(__('This allocation was already released.'), 'allocation_released');
        }

        $allocation->update(['active_unit_key' => null, 'released_at' => now(), 'released_by' => $actor->id, 'release_reason' => $reason]);

        $unit = InventoryUnit::query()->lockForUpdate()->findOrFail($allocation->inventory_unit_id);
        $unit->update(['status' => UnitStatus::Available]);
        $unit->movements()->create([
            'type' => MovementType::Released,
            'from_status' => UnitStatus::Allocated->value,
            'to_status' => UnitStatus::Available->value,
            'order_id' => $allocation->order_id,
            'remarks' => $reason,
            'user_id' => $actor->id,
        ]);
    }

    /**
     * Inventory task: Pending (nothing allocated) → In progress (partly) → Completed (all units).
     */
    private function syncTask(User $actor, Order $order): void
    {
        $task = FulfilmentTask::query()
            ->whereHas('fulfilment', fn ($query) => $query->where('order_id', $order->id))
            ->whereHas('type', fn ($query) => $query->where('driven_by', FulfilmentTaskType::DRIVEN_BY_ALLOCATION))
            ->first();

        if ($task === null) {
            return;
        }

        $allocated = $order->activeAllocations()->count();
        $required = $order->unitsRequired();

        $this->tasks->followCode($actor, $task, match (true) {
            $allocated === 0 => FulfilmentTask::STAGE_PENDING,
            $allocated < $required => 'IN_PROGRESS',
            default => 'COMPLETED',
        });
    }

    private function assertCan(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new BusinessRuleException(__('You are not allowed to do this.'), 'not_allowed');
        }
    }
}
