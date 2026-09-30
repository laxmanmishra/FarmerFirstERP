<?php

namespace Tests\Feature\Fulfilment;

use App\Actions\Inventory\AllocationFlow;
use App\Actions\Inventory\ReceiveStock;
use App\Actions\Inventory\UnitFlow;
use App\Actions\Orders\CancelOrder;
use App\Enums\MovementType;
use App\Enums\UnitStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\FulfilmentTask;
use App\Models\InventoryUnit;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockAllocation;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\CreatesCrmData;
use Tests\Concerns\CreatesOrderData;
use Tests\Concerns\CreatesSalesData;
use Tests\TestCase;

/**
 * INV-01 and mandatory test T5: product ≠ unit, GRN, allocation, reallocation with history,
 * transfers, and no unit allocated to two orders at once (INV-04).
 */
class InventoryTest extends TestCase
{
    use CreatesCrmData, CreatesOrderData, CreatesSalesData, RefreshDatabase;

    private User $manager;

    private User $salesman;

    private User $storekeeper;

    private User $inventoryManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->manager = $this->crmUser('Sales Manager');
        $this->salesman = $this->crmUser('Salesman', $this->manager);
        $this->storekeeper = $this->crmUser('Inventory Employee', department: 'INVENTORY');
        $this->inventoryManager = $this->crmUser('Inventory Manager', department: 'INVENTORY');
    }

    public function test_grn_creates_available_units_and_rejects_duplicate_numbers(): void
    {
        $product = Product::factory()->create();
        [$unit] = $this->receiveUnits($this->storekeeper, $product);

        $this->assertSame(UnitStatus::Available, $unit->status);
        $this->assertMatchesRegularExpression('#^GRN/#', $unit->inward->grn_no);
        $this->assertSame(MovementType::Inward, $unit->movements->sole()->type);

        $location = StockLocation::query()->firstOrFail();
        $header = ['supplier_name' => 'OEM', 'supplier_invoice_no' => null, 'supplier_invoice_date' => null, 'received_on' => today()->toDateString(), 'remarks' => null];
        $row = ['product_id' => $product->id, 'product_variant_id' => null, 'chassis_no' => strtolower($unit->chassis_no), 'engine_no' => 'NEW1', 'colour' => null, 'model_year' => null, 'purchase_cost' => null];

        foreach ([[[$row], 'duplicate_unit'], [[['chassis_no' => 'A1'] + $row, ['chassis_no' => 'A1', 'engine_no' => 'NEW2'] + $row], 'duplicate_in_grn']] as [$units, $rule]) {
            try {
                app(ReceiveStock::class)->handle($this->storekeeper, $location, $header, $units);
                $this->fail("Expected {$rule}");
            } catch (BusinessRuleException $exception) {
                $this->assertSame($rule, $exception->rule);
            }
        }

        $this->expectException(BusinessRuleException::class);
        app(ReceiveStock::class)->handle($this->salesman, $location, $header, [['chassis_no' => 'Z9', 'engine_no' => 'Z9'] + $row]);
    }

    public function test_allocation_completes_the_inventory_task_and_release_reverts_it(): void
    {
        $order = $this->bookOrder($this->salesman, $this->manager);
        [$unit] = $this->receiveUnits($this->storekeeper, $this->orderProduct($order));
        $flow = app(AllocationFlow::class);

        $allocation = $flow->allocate($this->storekeeper, $order, $unit);

        $this->assertSame(UnitStatus::Allocated, $unit->fresh()->status);
        $this->assertSame($unit->id, $allocation->active_unit_key);
        $this->assertSame('COMPLETED', $this->inventoryTask($order)->stage->code);

        try {
            $flow->allocate($this->storekeeper, $order, $this->receiveUnits($this->storekeeper, $this->orderProduct($order))[0]);
            $this->fail('Order needs one unit only.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('order_fully_allocated', $exception->rule);
        }

        try {
            $flow->release($this->storekeeper, $allocation, 'Customer changed mind');
            $this->fail('Release needs inventory.reallocate.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('not_allowed', $exception->rule);
        }

        $flow->release($this->inventoryManager, $allocation, 'Customer changed colour');

        $this->assertSame(UnitStatus::Available, $unit->fresh()->status);
        $this->assertNull($allocation->fresh()->active_unit_key);
        $this->assertSame(FulfilmentTask::STAGE_PENDING, $this->inventoryTask($order)->stage->code);
        $this->assertSame(['released', 'allocated', 'inward'], $unit->movements()->pluck('type')->map->value->all());
    }

    public function test_t5_same_chassis_cannot_be_allocated_to_two_orders(): void
    {
        $product = Product::factory()->create();
        $first = $this->bookOrder($this->salesman, $this->manager, product: $product);
        $second = $this->bookOrder($this->salesman, $this->manager, product: $product);
        [$unit] = $this->receiveUnits($this->storekeeper, $product);

        app(AllocationFlow::class)->allocate($this->storekeeper, $first, $unit);

        try {
            app(AllocationFlow::class)->allocate($this->storekeeper, $second, $unit->fresh());
            $this->fail('Second allocation must be refused.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('unit_not_available', $exception->rule);
        }

        // Even bypassing the service, the database refuses a second active allocation.
        $this->expectException(UniqueConstraintViolationException::class);
        StockAllocation::create(['inventory_unit_id' => $unit->id, 'order_id' => $second->id, 'active_unit_key' => $unit->id, 'allocated_at' => now()]);
    }

    public function test_product_branch_and_order_state_are_checked(): void
    {
        $order = $this->bookOrder($this->salesman, $this->manager);
        [$other] = $this->receiveUnits($this->storekeeper, Product::factory()->create());
        $flow = app(AllocationFlow::class);

        try {
            $flow->allocate($this->storekeeper, $order, $other);
            $this->fail('Wrong product.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('unit_product_mismatch', $exception->rule);
        }

        $branch = Branch::create(['company_id' => Branch::query()->value('company_id'), 'code' => 'B2', 'name' => 'Second branch']);
        $remote = StockLocation::create(['branch_id' => $branch->id, 'code' => 'REMOTE', 'name' => 'Remote yard', 'type' => 'yard']);
        [$unit] = $this->receiveUnits($this->storekeeper, $this->orderProduct($order));
        app(UnitFlow::class)->transfer($this->crmUser('Inventory Manager', department: 'INVENTORY'), $unit, $remote, 'Stock balancing');

        try {
            $flow->allocate($this->storekeeper, $order, $unit->fresh());
            $this->fail('Other branch.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('unit_other_branch', $exception->rule);
        }

        $this->assertSame(MovementType::Transfer, $unit->movements()->first()->type);
    }

    public function test_reallocation_swaps_units_and_keeps_history(): void
    {
        $order = $this->bookOrder($this->salesman, $this->manager);
        [$red, $blue] = $this->receiveUnits($this->storekeeper, $this->orderProduct($order), 2);
        $flow = app(AllocationFlow::class);
        $allocation = $flow->allocate($this->storekeeper, $order, $red);

        $new = $flow->reallocate($this->inventoryManager, $allocation, $blue, 'Customer prefers blue');

        $this->assertSame(UnitStatus::Available, $red->fresh()->status);
        $this->assertSame(UnitStatus::Allocated, $blue->fresh()->status);
        $this->assertSame(2, $order->allocations()->count());
        $this->assertSame([$new->id], $order->activeAllocations()->pluck('id')->all());
        $this->assertSame('Customer prefers blue', $allocation->fresh()->release_reason);
        $this->assertSame('COMPLETED', $this->inventoryTask($order)->stage->code);

        $this->expectException(LogicException::class);
        $red->movements()->first()->delete();
    }

    public function test_blocking_and_cancellation_return_units(): void
    {
        $order = $this->bookOrder($this->salesman, $this->manager);
        [$unit, $spare] = $this->receiveUnits($this->storekeeper, $this->orderProduct($order), 2);
        $units = app(UnitFlow::class);

        $units->block($this->inventoryManager, $spare, 'Scratched bonnet');
        $this->assertSame(UnitStatus::Blocked, $spare->fresh()->status);

        try {
            app(AllocationFlow::class)->allocate($this->storekeeper, $order, $spare->fresh());
            $this->fail('Blocked unit.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('unit_not_available', $exception->rule);
        }

        $units->unblock($this->inventoryManager, $spare->fresh(), 'Repainted');
        app(AllocationFlow::class)->allocate($this->storekeeper, $order, $unit);
        app(CancelOrder::class)->handle($this->crmUser('Owner'), $order->fresh(), 'Customer withdrew');

        $this->assertSame(UnitStatus::Available, $unit->fresh()->status);
        $this->assertSame(0, $order->activeAllocations()->count());
        $this->assertSame(1, InventoryUnit::query()->where('status', UnitStatus::Available)->whereKey($spare->id)->count());
    }

    private function inventoryTask(Order $order): FulfilmentTask
    {
        return $order->fulfilment->tasks()->whereHas('type', fn ($query) => $query->where('code', 'INVENTORY'))->with('stage')->firstOrFail();
    }
}
