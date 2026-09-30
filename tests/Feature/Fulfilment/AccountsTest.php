<?php

namespace Tests\Feature\Fulfilment;

use App\Actions\Accounts\AccountFileFlow;
use App\Actions\Accounts\PaymentFlow;
use App\Actions\Accounts\RefundFlow;
use App\Actions\Orders\CancelOrder;
use App\Enums\AccountPosition;
use App\Enums\PaymentKind;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AccountFile;
use App\Models\AuditLog;
use App\Models\FulfilmentTask;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\CreatesCrmData;
use Tests\Concerns\CreatesOrderData;
use Tests\Concerns\CreatesSalesData;
use Tests\TestCase;

/**
 * ACC-01 and mandatory test T7: payments verified and cleared one by one, balances
 * computed, reversals reference the original, refunds need someone else's approval.
 */
class AccountsTest extends TestCase
{
    use CreatesCrmData, CreatesOrderData, CreatesSalesData, RefreshDatabase;

    private User $cashier;

    private User $verifier;

    private User $accountsManager;

    private Order $order;

    private AccountFile $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $manager = $this->crmUser('Sales Manager');
        $salesman = $this->crmUser('Salesman', $manager);
        $this->cashier = $this->crmUser('Accounts Employee', department: 'ACCOUNTS');
        $this->verifier = $this->crmUser('Accounts Employee', department: 'ACCOUNTS');
        $this->accountsManager = $this->crmUser('Accounts Manager', department: 'ACCOUNTS');
        $this->order = $this->bookOrder($salesman, $manager);
        $this->file = $this->order->accountFile;
    }

    public function test_account_file_receivable_comes_from_the_order(): void
    {
        $this->assertMatchesRegularExpression('#^ACC/#', $this->file->file_no);
        $this->assertSame($this->order->order_value, $this->file->receivable_amount);
        $this->assertSame('PAYMENT_PENDING', $this->file->stage->code);
        $this->assertSame(AccountPosition::NotPaid, $this->fresh()->position());
    }

    public function test_payment_is_verified_by_someone_else_then_cleared_with_a_receipt(): void
    {
        $flow = app(PaymentFlow::class);
        $payment = $flow->record($this->cashier, $this->file, $this->payment('cheque', '100000', 'CHQ-001'));

        $this->assertSame(PaymentStatus::PendingVerification, $payment->status);
        $this->assertMatchesRegularExpression('#^PAY/#', $payment->payment_no);
        $this->assertSame('0.00', $this->fresh()->clearedTotal());

        try {
            $flow->verify($this->cashier, $payment);
            $this->fail('Recorder cannot verify.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('self_verification', $exception->rule);
        }

        $flow->verify($this->verifier, $payment->fresh());
        $this->assertMatchesRegularExpression('#^RCP/HO/#', $payment->fresh()->receipt->receipt_no);
        $this->assertSame('100000.00', $this->fresh()->unclearedTotal());

        $flow->clear($this->verifier, $payment->fresh());
        $file = $this->fresh();
        $this->assertSame('100000.00', $file->clearedTotal());
        $this->assertSame(AccountPosition::Short, $file->position());
    }

    public function test_recording_rules(): void
    {
        $flow = app(PaymentFlow::class);

        foreach ([
            [$this->payment('cash', '0'), 'invalid_amount'],
            [$this->payment('upi', '1000', ''), 'reference_required'],
            [['payer_type' => 'financer'] + $this->payment('finance_disbursement', '1000', 'X'), 'no_finance'],
            [$this->payment('finance_disbursement', '1000', 'X'), 'mode_payer_mismatch'],
            [['received_on' => today()->addDay()->toDateString()] + $this->payment('cash', '1000'), 'future_date'],
        ] as [$data, $rule]) {
            try {
                $flow->record($this->cashier, $this->file, $data);
                $this->fail("Expected {$rule}");
            } catch (BusinessRuleException $exception) {
                $this->assertSame($rule, $exception->rule);
            }
        }

        $payment = $flow->record($this->cashier, $this->file, $this->payment('cash', '5000'));
        $this->expectException(LogicException::class);
        $payment->update(['amount' => '1']);
    }

    public function test_completion_is_refused_while_money_is_short_and_the_task_follows(): void
    {
        $flows = app(AccountFileFlow::class);

        try {
            $flows->move($this->verifier, $this->fresh(), $this->stage('PAYMENT_CLEARED'), null);
            $this->fail('Short balance must block completion.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('balance_outstanding', $exception->rule);
        }

        $this->payInFull();
        $flows->move($this->verifier, $this->fresh(), $this->stage('PAYMENT_CLEARED'), null);

        $this->assertSame(AccountPosition::Cleared, $this->fresh()->position());
        $this->assertSame('COMPLETED', $this->accountsTask()->stage->code);
    }

    public function test_t7_reversal_keeps_the_original_and_reopens_the_file(): void
    {
        $payment = $this->payInFull();
        app(AccountFileFlow::class)->move($this->verifier, $this->fresh(), $this->stage('PAYMENT_CLEARED'), null);

        try {
            app(PaymentFlow::class)->reverse($this->verifier, $payment->fresh(), 'Wrong file');
            $this->fail('Reversal needs accounts.reverse.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('not_allowed', $exception->rule);
        }

        $reversal = app(PaymentFlow::class)->reverse($this->accountsManager, $payment->fresh(), 'Posted to wrong customer');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Reversed, $payment->status);
        $this->assertSame($this->order->order_value, $payment->amount);
        $this->assertSame(PaymentKind::Reversal, $reversal->kind);
        $this->assertSame($payment->id, $reversal->reverses_payment_id);
        $this->assertSame('-'.$this->order->order_value, $reversal->amount);
        $this->assertNotNull($payment->receipt->cancelled_at);

        $file = $this->fresh();
        $this->assertSame('0.00', $file->clearedTotal());
        $this->assertSame($this->order->order_value, $file->balance());
        $this->assertSame(AccountFileFlow::STAGE_SHORT, $file->stage->code);
        $this->assertSame('IN_PROGRESS', $this->accountsTask()->stage->code);
        $this->assertTrue(AuditLog::query()->where('auditable_type', $payment->getMorphClass())->where('auditable_id', $payment->id)->where('event', 'updated')->exists());

        $this->expectException(BusinessRuleException::class);
        app(PaymentFlow::class)->reverse($this->accountsManager, $payment->fresh(), 'again');
    }

    public function test_bounced_cheque_is_returned_and_cash_cannot_bounce(): void
    {
        $flow = app(PaymentFlow::class);
        $cheque = $flow->record($this->cashier, $this->file, $this->payment('cheque', '50000', 'CHQ-9'));
        $flow->verify($this->verifier, $cheque);
        $flow->clear($this->verifier, $cheque->fresh());
        $flow->markReturned($this->verifier, $cheque->fresh(), 'Insufficient funds');

        $this->assertSame(PaymentStatus::Returned, $cheque->fresh()->status);
        $this->assertSame('0.00', $this->fresh()->clearedTotal());

        $cash = $flow->record($this->cashier, $this->file, $this->payment('cash', '1000'));
        $flow->verify($this->verifier, $cash);
        $this->expectException(BusinessRuleException::class);
        $flow->markReturned($this->verifier, $cash->fresh(), 'x');
    }

    public function test_refund_after_cancellation_needs_another_approver_and_is_a_negative_entry(): void
    {
        $flow = app(PaymentFlow::class);
        $payment = $flow->record($this->cashier, $this->file, $this->payment('neft', '25000', 'UTR1'));
        $flow->verify($this->verifier, $payment);
        $flow->clear($this->verifier, $payment->fresh());

        $refunds = app(RefundFlow::class);

        try {
            $refunds->request($this->cashier, $this->fresh(), '25000', 'Too early');
            $this->fail('Nothing is refundable while the order is live and short.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('refund_exceeds_available', $exception->rule);
        }

        app(CancelOrder::class)->handle($this->crmUser('Owner'), $this->order->fresh(), 'Customer withdrew');
        $refund = $refunds->request($this->accountsManager, $this->fresh(), '25000', 'Booking refund');

        try {
            $refunds->decide($this->accountsManager, $refund, true, null);
            $this->fail('Requester cannot approve.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('self_approval', $exception->rule);
        }

        $refunds->decide($this->crmUser('Owner'), $refund, true, null);
        $paid = $refunds->pay($this->verifier, $refund->fresh(), PaymentMode::Neft, 'UTR-REF');

        $this->assertSame(RefundStatus::Paid, $refund->fresh()->status);
        $this->assertSame(PaymentKind::Refund, $paid->kind);
        $this->assertSame('-25000.00', $paid->amount);
        $this->assertSame('0.00', $this->fresh()->clearedTotal());
        $this->assertSame('0.00', $this->fresh()->refundable());
    }

    private function payInFull(): Payment
    {
        $flow = app(PaymentFlow::class);
        $payment = $flow->record($this->cashier, $this->file, $this->payment('rtgs', $this->order->order_value, 'UTR-FULL'));
        $flow->verify($this->verifier, $payment);
        $flow->clear($this->verifier, $payment->fresh());

        return $payment->fresh();
    }

    private function fresh(): AccountFile
    {
        return AccountFile::query()->with(['payments', 'refunds', 'order', 'stage'])->findOrFail($this->file->id);
    }

    private function accountsTask(): FulfilmentTask
    {
        return $this->order->fulfilment->tasks()->whereHas('type', fn ($query) => $query->where('code', 'ACCOUNTS'))->with('stage')->firstOrFail();
    }

    private function stage(string $code): WorkflowStage
    {
        return WorkflowStage::findByCode(WorkflowDefinition::ACCOUNTS, $code);
    }

    /**
     * @return array<string, mixed>
     */
    private function payment(string $mode, string $amount, ?string $reference = null): array
    {
        return ['payer_type' => 'customer', 'mode' => $mode, 'amount' => $amount, 'reference_no' => $reference, 'instrument_date' => null,
            'bank_name' => null, 'received_on' => today()->toDateString(), 'remarks' => null];
    }
}
