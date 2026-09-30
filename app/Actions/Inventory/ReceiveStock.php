<?php

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Enums\UnitStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\InventoryUnit;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockInward;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\NumberSeriesService;
use Illuminate\Support\Facades\DB;

/**
 * Goods receipt (GRN, SRS §54): every received chassis becomes an AVAILABLE unit with an
 * inward stock movement. Chassis and engine numbers are unique across all stock.
 */
class ReceiveStock
{
    public function __construct(private readonly NumberSeriesService $numbers) {}

    /**
     * @param  array{supplier_name: string, supplier_invoice_no: ?string, supplier_invoice_date: ?string, received_on: string, remarks: ?string}  $header
     * @param  list<array{product_id: int, product_variant_id: ?int, chassis_no: string, engine_no: string, colour: ?string, model_year: ?int, purchase_cost: ?string}>  $units
     */
    public function handle(User $actor, StockLocation $location, array $header, array $units): StockInward
    {
        if (! $actor->can('inventory.inward')) {
            throw new BusinessRuleException(__('You are not allowed to receive stock.'), 'not_allowed');
        }

        if (! $location->is_active || ! $actor->canAccessBranch($location->branch_id)) {
            throw new BusinessRuleException(__('Choose an active location of your branch.'), 'location_not_allowed');
        }

        if ($units === []) {
            throw new BusinessRuleException(__('Add at least one unit.'), 'no_units');
        }

        $units = array_map(fn (array $unit) => [
            ...$unit,
            'chassis_no' => strtoupper(trim($unit['chassis_no'])),
            'engine_no' => strtoupper(trim($unit['engine_no'])),
        ], $units);

        foreach (['chassis_no' => __('chassis'), 'engine_no' => __('engine')] as $field => $label) {
            $numbers = array_column($units, $field);

            if (count($numbers) !== count(array_unique($numbers))) {
                throw new BusinessRuleException(__('The same :label number appears twice in this GRN.', ['label' => $label]), 'duplicate_in_grn');
            }

            if (($existing = InventoryUnit::query()->whereIn($field, $numbers)->value($field)) !== null) {
                throw new BusinessRuleException(__(':label number :no is already in stock records.', ['label' => ucfirst($label), 'no' => $existing]), 'duplicate_unit', ['number' => $existing]);
            }
        }

        foreach ($units as $unit) {
            $product = Product::query()->find($unit['product_id']);

            if ($product === null || ! $product->is_active) {
                throw new BusinessRuleException(__('Choose an active product for every unit.'), 'invalid_product');
            }

            if ($unit['product_variant_id'] && ! ProductVariant::query()->whereKey($unit['product_variant_id'])->where('product_id', $product->id)->exists()) {
                throw new BusinessRuleException(__('The variant does not belong to :product.', ['product' => $product->name]), 'invalid_variant');
            }
        }

        return DB::transaction(function () use ($actor, $location, $header, $units): StockInward {
            $location->loadMissing('branch');

            $inward = StockInward::create([
                'grn_no' => $this->numbers->next('stock_inward', $location->branch),
                'branch_id' => $location->branch_id,
                'stock_location_id' => $location->id,
                ...$header,
            ]);

            foreach ($units as $unit) {
                $created = $inward->units()->create([
                    ...$unit,
                    'product_variant_id' => $unit['product_variant_id'] ?: null,
                    'purchase_cost' => $unit['purchase_cost'] ?: null,
                    'branch_id' => $location->branch_id,
                    'stock_location_id' => $location->id,
                    'status' => UnitStatus::Available,
                    'received_on' => $header['received_on'],
                ]);

                $created->movements()->create([
                    'type' => MovementType::Inward,
                    'to_location_id' => $location->id,
                    'to_status' => UnitStatus::Available->value,
                    'remarks' => $inward->grn_no,
                    'user_id' => $actor->id,
                ]);
            }

            return $inward;
        });
    }
}
