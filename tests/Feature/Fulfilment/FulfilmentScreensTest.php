<?php

namespace Tests\Feature\Fulfilment;

use App\Enums\PaymentStatus;
use App\Enums\UnitStatus;
use App\Livewire\Fulfilment\Accounts\Show as AccountShow;
use App\Livewire\Fulfilment\Finance\Financers;
use App\Livewire\Fulfilment\Finance\Show as FinanceShow;
use App\Livewire\Fulfilment\Inventory\Index as InventoryIndex;
use App\Livewire\Fulfilment\Inventory\Unit as UnitShow;
use App\Models\Financer;
use App\Models\InventoryUnit;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockLocation;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesCrmData;
use Tests\Concerns\CreatesOrderData;
use Tests\Concerns\CreatesSalesData;
use Tests\TestCase;

/**
 * Retail & Finance, Accounts and Inventory screens.
 */
class FulfilmentScreensTest extends TestCase
{
    use CreatesCrmData, CreatesOrderData, CreatesSalesData, RefreshDatabase;

    private User $manager;

    private User $salesman;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->manager = $this->crmUser('Sales Manager');
        $this->salesman = $this->crmUser('Salesman', $this->manager);
        $this->order = $this->bookOrder($this->salesman, $this->manager, ['finance_required' => true, 'finance_amount' => '300000']);
    }

    public function test_department_screens_render_and_are_permission_guarded(): void
    {
        $finance = $this->crmUser('Retail Manager', department: 'RETAIL_FINANCE');
        $accounts = $this->crmUser('Accounts Manager', department: 'ACCOUNTS');
        $inventory = $this->crmUser('Inventory Manager', department: 'INVENTORY');
        $unit = $this->receiveUnits($inventory, $this->orderProduct($this->order))[0];

        $this->actingAs($finance);
        $this->get(route('fulfilment.finance.index'))->assertOk()->assertSee($this->order->financeFile->file_no);
        $this->get(route('fulfilment.finance.index', ['tab' => 'financers']))->assertOk();
        foreach (['details', 'activity', 'documents', 'timeline'] as $tab) {
            $this->get(route('fulfilment.finance.show', ['file' => $this->order->financeFile, 'tab' => $tab]))->assertOk();
        }

        $this->actingAs($accounts);
        $this->get(route('fulfilment.accounts.index'))->assertOk()->assertSee($this->order->accountFile->file_no);
        foreach (['verification', 'refunds'] as $tab) {
            $this->get(route('fulfilment.accounts.index', ['tab' => $tab]))->assertOk();
        }
        foreach (['ledger', 'documents', 'timeline'] as $tab) {
            $this->get(route('fulfilment.accounts.show', ['file' => $this->order->accountFile, 'tab' => $tab]))->assertOk();
        }

        $this->actingAs($inventory);
        foreach (['stock', 'allocation', 'grn', 'locations'] as $tab) {
            $this->get(route('fulfilment.inventory.index', ['tab' => $tab]))->assertOk();
        }
        $this->get(route('fulfilment.inventory.units.show', $unit))->assertOk()->assertSee($unit->chassis_no);

        $this->actingAs($this->salesman);
        $this->get(route('fulfilment.finance.index'))->assertForbidden();
        $this->get(route('fulfilment.accounts.index'))->assertForbidden();
        $this->get(route('fulfilment.inventory.index'))->assertForbidden();
        $this->get(route('sales.orders.show', ['order' => $this->order, 'tab' => 'fulfilment']))->assertOk()->assertDontSee($this->order->financeFile->file_no);
    }

    public function test_finance_workspace_flow(): void
    {
        $retail = $this->crmUser('Retail Manager', department: 'RETAIL_FINANCE');
        $financer = Financer::create(['code' => 'HDFC', 'name' => 'HDFC Bank', 'type' => 'bank']);
        $referral = WorkflowStage::findByCode(WorkflowDefinition::FINANCE, 'FINANCER_REFERRAL');

        Livewire::actingAs($retail)->test(FinanceShow::class, ['file' => $this->order->financeFile])
            ->set('details.financer_id', (string) $financer->id)
            ->set('details.sanctioned_amount', '300000')
            ->call('saveDetails')
            ->assertHasNoErrors()
            ->call('open', 'status')
            ->set('form.stage_id', (string) $referral->id)
            ->call('saveStatus')
            ->assertHasNoErrors()
            ->call('open', 'follow_up')
            ->set('form.purpose', 'Chase FI')
            ->set('form.employee_id', (string) $retail->employee->id)
            ->call('saveFollowUp')
            ->assertHasNoErrors()
            ->call('open', 'query')
            ->set('form.subject', 'Need land record')
            ->call('saveQuery')
            ->assertHasNoErrors();

        $file = $this->order->financeFile()->first();
        $this->assertSame($financer->id, $file->financer_id);
        $this->assertSame('FINANCER_REFERRAL', $file->stage->code);
        $this->assertSame(1, $file->followUps()->count());
        $this->assertSame(1, $file->queries()->count());

        Livewire::actingAs($retail)->test(Financers::class)
            ->call('editFinancer')->set('form.code', 'SBI')->set('form.name', 'State Bank')->call('saveFinancer')->assertHasNoErrors()
            ->call('editContact', $financer->id)->set('form.name', 'Officer')->set('form.mobile', '98765')->call('saveContact')->assertHasErrors('form.mobile');
        $this->assertSame(2, Financer::query()->count());
    }

    public function test_accounts_workspace_records_verifies_and_prints_a_receipt(): void
    {
        $cashier = $this->crmUser('Accounts Employee', department: 'ACCOUNTS');
        $verifier = $this->crmUser('Accounts Employee', department: 'ACCOUNTS');

        Livewire::actingAs($cashier)->test(AccountShow::class, ['file' => $this->order->accountFile])
            ->call('open', 'record')
            ->set('form.amount', '25000')
            ->call('recordPayment')
            ->assertHasNoErrors()
            ->assertDontSee(__('Verify'));

        $payment = Payment::query()->sole();

        Livewire::actingAs($verifier)->test(AccountShow::class, ['file' => $this->order->accountFile])
            ->call('verify', $payment->id)
            ->call('clear', $payment->id)
            ->assertDispatched('toast', type: 'success');

        $this->assertSame(PaymentStatus::Cleared, $payment->fresh()->status);
        $this->actingAs($verifier)->get(route('fulfilment.accounts.receipt', $payment->fresh()->receipt))->assertOk()->assertSee('25,000.00');
        $this->actingAs($this->salesman)->get(route('fulfilment.accounts.receipt', $payment->fresh()->receipt))->assertForbidden();

        // Recording rule errors surface as a toast, not a crash.
        Livewire::actingAs($cashier)->test(AccountShow::class, ['file' => $this->order->accountFile])
            ->call('open', 'record')->set('form.mode', 'upi')->set('form.amount', '100')->call('recordPayment')
            ->assertDispatched('toast', type: 'error');
    }

    public function test_inventory_grn_and_allocation_from_the_queue(): void
    {
        $storekeeper = $this->crmUser('Inventory Employee', department: 'INVENTORY');
        $location = StockLocation::create(['branch_id' => $this->order->branch_id, 'code' => 'YARD1', 'name' => 'Main yard', 'type' => 'yard']);
        $product = $this->orderProduct($this->order);

        Livewire::actingAs($storekeeper)->test(InventoryIndex::class)
            ->call('openGrn')
            ->set('form.stock_location_id', (string) $location->id)
            ->set('form.supplier_name', 'Mahindra')
            ->set('rows.0.product_id', (string) $product->id)
            ->set('rows.0.chassis_no', 'mbx123')
            ->set('rows.0.engine_no', 'eng123')
            ->call('addRow')
            ->set('rows.1.chassis_no', 'MBX123')
            ->set('rows.1.engine_no', 'ENG124')
            ->call('saveGrn')
            ->assertHasErrors('rows.1.chassis_no')
            ->call('removeRow', 1)
            ->call('saveGrn')
            ->assertHasNoErrors();

        $unit = InventoryUnit::query()->sole();
        $this->assertSame('MBX123', $unit->chassis_no);

        Livewire::actingAs($storekeeper)->test(InventoryIndex::class, [])
            ->set('tab', 'allocation')
            ->assertSee($this->order->order_no)
            ->call('openAllocate', $this->order->id)
            ->assertSee('MBX123')
            ->call('allocate', $unit->id)
            ->assertDispatched('toast', type: 'success');

        $this->assertSame(UnitStatus::Allocated, $unit->fresh()->status);

        $manager = $this->crmUser('Inventory Manager', department: 'INVENTORY');
        Livewire::actingAs($manager)->test(UnitShow::class, ['unit' => $unit])
            ->call('open', 'release')
            ->call('save')
            ->assertHasErrors('form.reason')
            ->set('form.reason', 'Customer changed model')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(UnitStatus::Available, $unit->fresh()->status);
        $this->assertNotNull(Product::query()->find($product->id));
    }
}
