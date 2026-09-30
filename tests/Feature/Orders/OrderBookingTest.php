<?php

namespace Tests\Feature\Orders;

use App\Actions\Orders\CreateOrderFromDeal;
use App\Enums\FulfilmentStatus;
use App\Enums\RequirementState;
use App\Exceptions\BusinessRuleException;
use App\Models\Deal;
use App\Models\FulfilmentTask;
use App\Models\Order;
use App\Models\User;
use App\Notifications\FulfilmentTaskCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesCrmData;
use Tests\Concerns\CreatesOrderData;
use Tests\Concerns\CreatesSalesData;
use Tests\TestCase;

/**
 * ORD-01 / FUL-01: approval books the order with a frozen snapshot, one fulfilment and
 * the configured department tasks.
 */
class OrderBookingTest extends TestCase
{
    use CreatesCrmData, CreatesOrderData, CreatesSalesData, RefreshDatabase;

    private User $manager;

    private User $salesman;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->manager = $this->crmUser('Sales Manager');
        $this->salesman = $this->crmUser('Salesman', $this->manager);
    }

    public function test_approving_a_deal_books_an_order_with_the_commercial_snapshot(): void
    {
        $order = $this->bookOrder($this->salesman, $this->manager);
        $deal = $order->deal()->with('items')->first();

        $this->assertMatchesRegularExpression('#^ORD/\d{4}-\d{2}/00001$#', $order->order_no);
        $this->assertSame(Order::STAGE_BOOKED, $order->stage->code);
        $this->assertSame($deal->deal_value, $order->order_value);
        $this->assertSame($deal->customer_contribution, $order->customer_contribution);
        $this->assertSame($deal->primary_salesman_employee_id, $order->primary_salesman_employee_id);
        $this->assertEquals(json_decode(json_encode($deal->commercialSnapshot()), true), $order->deal_snapshot);
        $this->assertSame($deal->items->pluck('line_total')->all(), $order->items->pluck('line_total')->all());
        $this->assertSame(1, $order->statusHistory()->count());

        $fulfilment = $order->fulfilment;
        $this->assertMatchesRegularExpression('#^FUL/#', $fulfilment->fulfilment_no);
        $this->assertSame(FulfilmentStatus::Open, $fulfilment->status);
    }

    public function test_tasks_follow_the_deal_flags(): void
    {
        $order = $this->bookOrder($this->salesman, $this->manager, ['finance_required' => false, 'insurance_required' => false]);

        $states = $order->fulfilment->tasks()->with('type')->get()->mapWithKeys(fn (FulfilmentTask $task) => [$task->type->code => $task->requirement_state]);

        $this->assertSame(RequirementState::NotRequired, $states['FINANCE']);
        $this->assertSame(RequirementState::NotRequired, $states['INSURANCE']);
        $this->assertSame(RequirementState::Required, $states['ACCOUNTS']);
        $this->assertSame(RequirementState::Required, $states['RTO']);
        $this->assertSame(RequirementState::Required, $states['PDI']);
        $this->assertSame(7, $states->count());
        $this->assertTrue($order->fulfilment->tasks()->with('stage')->get()->every(fn (FulfilmentTask $task) => $task->stage->code === FulfilmentTask::STAGE_PENDING));
    }

    public function test_document_requirements_are_generated_from_the_rules(): void
    {
        $order = $this->bookOrder($this->salesman, $this->manager, ['finance_required' => true, 'finance_amount' => '300000']);

        $this->assertSame(RequirementState::Required, $this->requirement($order, 'AADHAAR', 'SALES')->requirement_state);
        $this->assertSame(RequirementState::Required, $this->requirement($order, 'DO')->requirement_state);
        $this->assertTrue($this->requirement($order, 'DO')->blocks_delivery);
        $this->assertSame($order->primary_salesman_employee_id, $this->requirement($order, 'AADHAAR', 'SALES')->responsible_employee_id);
        $this->assertSame($order->order_date->addDays(2)->toDateString(), $this->requirement($order, 'AADHAAR', 'SALES')->due_date->toDateString());

        $cash = $this->bookOrder($this->salesman, $this->manager);
        $this->assertSame(RequirementState::NotRequired, $this->requirement($cash, 'DO')->requirement_state);
    }

    public function test_booking_is_idempotent(): void
    {
        $order = $this->bookOrder($this->salesman, $this->manager);

        $again = app(CreateOrderFromDeal::class)->handle($order->deal, $this->manager);

        $this->assertTrue($again->is($order));
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(7, FulfilmentTask::query()->count());
    }

    public function test_only_approved_deals_become_orders(): void
    {
        $deal = $this->win($this->salesman, $this->validatedEnquiry($this->salesman));

        $this->expectException(BusinessRuleException::class);
        app(CreateOrderFromDeal::class)->handle($deal, $this->manager);
    }

    public function test_departments_with_required_tasks_are_notified(): void
    {
        Notification::fake();
        $accounts = $this->crmUser('Accounts Employee', department: 'ACCOUNTS');
        $retail = $this->crmUser('Retail Employee', department: 'RETAIL_FINANCE');

        $this->bookOrder($this->salesman, $this->manager);

        Notification::assertSentTo($accounts, FulfilmentTaskCreated::class);
        Notification::assertNotSentTo($retail, FulfilmentTaskCreated::class); // cash deal: finance not required
    }

    public function test_salesmen_see_only_their_own_orders_and_departments_see_all(): void
    {
        $order = $this->bookOrder($this->salesman, $this->manager);
        $other = $this->crmUser('Salesman', $this->manager);
        $rto = $this->crmUser('RTO Employee', department: 'RTO');

        $this->assertTrue(Order::query()->visibleTo($this->salesman)->whereKey($order->id)->exists());
        $this->assertFalse(Order::query()->visibleTo($other)->whereKey($order->id)->exists());
        $this->assertTrue(Order::query()->visibleTo($rto)->whereKey($order->id)->exists());
        $this->assertTrue(Deal::query()->whereKey($order->deal_id)->exists());
    }
}
