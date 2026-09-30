<?php

namespace Tests\Feature\Fulfilment;

use App\Actions\Finance\FinanceFileFlow;
use App\Actions\FollowUps\CompleteFollowUp;
use App\Actions\FollowUps\ScheduleFollowUp;
use App\Actions\Fulfilment\FulfilmentTaskFlow;
use App\Actions\Orders\CancelOrder;
use App\Actions\Queries\FileQueryFlow;
use App\Enums\QueryStatus;
use App\Enums\RequirementState;
use App\Exceptions\BusinessRuleException;
use App\Models\FinanceFile;
use App\Models\Financer;
use App\Models\FulfilmentTask;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCrmData;
use Tests\Concerns\CreatesOrderData;
use Tests\Concerns\CreatesSalesData;
use Tests\TestCase;

/**
 * FIN-01: finance file per financed order, status per external financer, queries,
 * follow-ups; the order's finance task follows the file.
 */
class FinanceFileTest extends TestCase
{
    use CreatesCrmData, CreatesOrderData, CreatesSalesData, RefreshDatabase;

    private User $manager;

    private User $salesman;

    private User $retail;

    private Order $order;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->manager = $this->crmUser('Sales Manager');
        $this->salesman = $this->crmUser('Salesman', $this->manager);
        $this->retail = $this->crmUser('Retail Employee', department: 'RETAIL_FINANCE');
        $this->order = $this->bookOrder($this->salesman, $this->manager, ['finance_required' => true, 'finance_amount' => '300000']);
        $this->financer = Financer::create(['code' => 'HDFC', 'name' => 'HDFC Bank', 'type' => 'bank']);
    }

    public function test_finance_file_is_opened_only_for_financed_orders(): void
    {
        $file = $this->order->financeFile;

        $this->assertMatchesRegularExpression('#^FIN/#', $file->file_no);
        $this->assertSame('FILE_CREATED', $file->stage->code);
        $this->assertSame('300000.00', $file->loan_amount);
        $this->assertSame($this->task('FINANCE')->id, $file->fulfilment_task_id);
        $this->assertNotNull($this->order->accountFile);

        $cash = $this->bookOrder($this->salesman, $this->manager);
        $this->assertNull($cash->financeFile);
        $this->assertNotNull($cash->accountFile);

        // Switching finance on later opens the file (idempotently).
        $task = $cash->fulfilment->tasks()->whereHas('type', fn ($query) => $query->where('code', 'FINANCE'))->firstOrFail();
        app(FulfilmentTaskFlow::class)->changeRequirement($this->manager, $task, RequirementState::Required, 'Customer now takes a loan');
        $this->assertNotNull($cash->fresh()->financeFile);
        $this->assertSame(2, FinanceFile::query()->count());
    }

    public function test_status_moves_drive_the_finance_task(): void
    {
        $flow = app(FinanceFileFlow::class);
        $file = $this->order->financeFile;

        $flow->move($this->retail, $file, $this->stage('FINANCER_REFERRAL'), null);
        $this->assertSame('IN_PROGRESS', $this->task('FINANCE')->stage->code);
        $this->assertSame('IN_FULFILMENT', $this->order->fresh()->stage->code);

        $flow->move($this->retail, $file->fresh(), $this->stage('ON_HOLD'), 'Customer travelling');
        $this->assertSame('ON_HOLD', $this->task('FINANCE')->stage->code);

        try {
            $flow->move($this->retail, $file->fresh(), $this->stage('FINANCE_COMPLETED'), null);
            $this->fail('Completion needs financer and sanction.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('finance_incomplete', $exception->rule);
        }

        $flow->updateDetails($this->retail, $file->fresh(), $this->details(['financer_id' => $this->financer->id, 'sanctioned_amount' => '300000']));
        $flow->move($this->retail, $file->fresh(), $this->stage('FINANCE_COMPLETED'), null);

        $this->assertSame('COMPLETED', $this->task('FINANCE')->stage->code);
        $this->assertNotNull($this->task('FINANCE')->completed_at);
        $this->assertGreaterThanOrEqual(4, $file->statusHistory()->count());
    }

    public function test_task_cannot_be_moved_by_hand_and_system_stage_is_protected(): void
    {
        try {
            app(FulfilmentTaskFlow::class)->move($this->retail, $this->task('FINANCE'), WorkflowStage::findByCode(WorkflowDefinition::FULFILMENT_TASK, 'IN_PROGRESS'), null);
            $this->fail('File-driven task must not move by hand.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('task_driven_by_file', $exception->rule);
        }

        $this->expectException(BusinessRuleException::class);
        app(FinanceFileFlow::class)->move($this->retail, $this->order->financeFile, $this->stage('CANCELLED'), 'x');
    }

    public function test_details_are_validated_and_permission_checked(): void
    {
        $flow = app(FinanceFileFlow::class);
        $file = $this->order->financeFile;
        $other = Financer::create(['code' => 'SBI', 'name' => 'SBI', 'type' => 'bank']);
        $contact = $other->contacts()->create(['name' => 'Field officer', 'mobile' => '9876500000']);

        foreach ([
            [$this->salesman, [], 'not_allowed'],
            [$this->retail, ['financer_id' => $this->financer->id, 'financer_contact_id' => $contact->id], 'contact_mismatch'],
            [$this->retail, ['sanctioned_amount' => '99999999'], 'amount_exceeds_order'],
            [$this->retail, ['do_date' => today()->toDateString(), 'do_valid_until' => today()->subDay()->toDateString()], 'do_validity'],
        ] as [$user, $details, $rule]) {
            try {
                $flow->updateDetails($user, $file->fresh(), $this->details($details));
                $this->fail("Expected {$rule}");
            } catch (BusinessRuleException $exception) {
                $this->assertSame($rule, $exception->rule);
            }
        }
    }

    public function test_queries_follow_their_lifecycle(): void
    {
        $flow = app(FileQueryFlow::class);
        $query = $flow->raise($this->retail, $this->order->financeFile, 'finance.update', 'HDFC Bank', 'Land record mismatch', null, today()->addDays(2), null);

        $this->assertSame(QueryStatus::Open, $query->status);

        try {
            $flow->update($this->retail, $query, 'finance.update', QueryStatus::Resolved, null);
            $this->fail('Resolution needs a response.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('response_required', $exception->rule);
        }

        $flow->update($this->retail, $query, 'finance.update', QueryStatus::Submitted, 'Sent corrected 7/12 extract');
        $flow->update($this->retail, $query->fresh(), 'finance.update', QueryStatus::Resolved, null);

        $query->refresh();
        $this->assertSame(QueryStatus::Resolved, $query->status);
        $this->assertSame($this->retail->id, $query->resolved_by);

        $this->expectException(BusinessRuleException::class);
        $flow->update($this->retail, $query, 'finance.update', QueryStatus::Open, null);
    }

    public function test_follow_ups_work_on_finance_files(): void
    {
        $followUp = app(ScheduleFollowUp::class)->forRecord($this->retail, $this->order->financeFile, $this->retail->employee, 'CALL', now()->addDay(), 'Chase sanction letter');

        $this->assertSame($this->order->branch_id, $followUp->branch_id);

        $retailManager = $this->crmUser('Retail Manager', department: 'RETAIL_FINANCE');
        app(CompleteFollowUp::class)->handle($retailManager, $followUp->fresh(['followable', 'assignee']), 'Bank confirmed', [
            'type_code' => 'CALL', 'due_at' => now()->addDays(3), 'purpose' => 'Collect DO',
        ]);

        $this->assertSame(2, $this->order->financeFile->followUps()->count());
        $this->artisan('crm:follow-up-reminders')->assertSuccessful();
    }

    public function test_cancelling_the_order_cancels_the_finance_file(): void
    {
        app(CancelOrder::class)->handle($this->crmUser('Owner'), $this->order->fresh(), 'Customer withdrew');

        $this->assertSame('CANCELLED', $this->order->financeFile()->first()->stage->code);
        $this->assertSame(FulfilmentTask::STAGE_CANCELLED, $this->task('FINANCE')->stage->code);
    }

    private function task(string $code): FulfilmentTask
    {
        return $this->order->fulfilment->tasks()->whereHas('type', fn ($query) => $query->where('code', $code))->with(['type', 'stage'])->firstOrFail();
    }

    private function stage(string $code): WorkflowStage
    {
        return WorkflowStage::findByCode(WorkflowDefinition::FINANCE, $code);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function details(array $overrides = []): array
    {
        return $overrides + array_fill_keys(['financer_id', 'financer_contact_id', 'sanctioned_amount', 'down_payment', 'tenure_months', 'interest_rate', 'emi_amount',
            'loan_account_no', 'do_number', 'do_date', 'do_amount', 'do_valid_until', 'disbursed_amount', 'disbursed_on', 'remarks'], null);
    }
}
