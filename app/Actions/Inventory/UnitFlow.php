<?php

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Enums\UnitStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\InventoryUnit;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Transfers and blocking of physical units (SRS §55–56); every change is a stock movement.
 */
class UnitFlow
{
    public function transfer(User $actor, InventoryUnit $unit, StockLocation $to, ?string $remarks): InventoryUnit
    {
        if (! $actor->can('inventory.transfer')) {
            throw new BusinessRuleException(__('You are not allowed to transfer stock.'), 'not_allowed');
        }

        if (! $to->is_active || $to->id === $unit->stock_location_id) {
            throw new BusinessRuleException(__('Choose another active location.'), 'invalid_location');
        }

        if ($to->branch_id !== $unit->branch_id && $unit->status === UnitStatus::Allocated) {
            throw new BusinessRuleException(__('Release the allocation before moving the unit to another branch.'), 'unit_allocated');
        }

        DB::transaction(function () use ($actor, $unit, $to, $remarks): void {
            $from = $unit->stock_location_id;
            $unit->update(['stock_location_id' => $to->id, 'branch_id' => $to->branch_id]);
            $unit->movements()->create([
                'type' => MovementType::Transfer,
                'from_location_id' => $from,
                'to_location_id' => $to->id,
                'remarks' => $remarks,
                'user_id' => $actor->id,
            ]);
        });

        return $unit;
    }

    /**
     * Takes a damaged / disputed unit out of sale. Allocated units must be released first.
     */
    public function block(User $actor, InventoryUnit $unit, string $reason): InventoryUnit
    {
        $this->assertManager($actor);

        if ($unit->status !== UnitStatus::Available) {
            throw new BusinessRuleException(__('Only available units can be blocked.'), 'unit_not_available');
        }

        if (trim($reason) === '') {
            throw new BusinessRuleException(__('Give the reason for blocking.'), 'reason_required');
        }

        return $this->setStatus($actor, $unit, UnitStatus::Blocked, MovementType::Blocked, $reason);
    }

    public function unblock(User $actor, InventoryUnit $unit, ?string $remarks): InventoryUnit
    {
        $this->assertManager($actor);

        if ($unit->status !== UnitStatus::Blocked) {
            throw new BusinessRuleException(__('The unit is not blocked.'), 'unit_not_blocked');
        }

        return $this->setStatus($actor, $unit, UnitStatus::Available, MovementType::Unblocked, $remarks);
    }

    private function setStatus(User $actor, InventoryUnit $unit, UnitStatus $status, MovementType $type, ?string $remarks): InventoryUnit
    {
        DB::transaction(function () use ($actor, $unit, $status, $type, $remarks): void {
            $from = $unit->status;
            $unit->update(['status' => $status, 'status_reason' => $status === UnitStatus::Blocked ? $remarks : null]);
            $unit->movements()->create([
                'type' => $type,
                'from_status' => $from->value,
                'to_status' => $status->value,
                'remarks' => $remarks,
                'user_id' => $actor->id,
            ]);
        });

        return $unit;
    }

    private function assertManager(User $actor): void
    {
        if (! $actor->can('inventory.reallocate')) {
            throw new BusinessRuleException(__('Only inventory managers can block or unblock units.'), 'not_allowed');
        }
    }
}
