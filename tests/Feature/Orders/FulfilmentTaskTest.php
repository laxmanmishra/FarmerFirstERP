<?php

namespace Tests\Feature\Orders;

use App\Actions\Fulfilment\FulfilmentTaskFlow;
use App\Actions\Orders\CancelOrder;
use App\Enums\FulfilmentStatus;
use App\Enums\RequirementState;
use App\Exceptions\BusinessRuleException;
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
 * FUL-01: requirement state is independent of the operational stage; only the owning
 * department works a task; cancellation keeps everything.
 */
class FulfilmentTaskTest extends TestCase
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
        $this->order = $this->bookOrder($this->salesman, $this->manager);
    }

    public function test_owning_department_moves_its_task_and_the_order_enters_fulfilment(): void
    {
        $rto = $this->crmUser('RTO Employee', department: 'RTO');
        $task = $this->task('RTO');

        app(FulfilmentTaskFlow::class)->move($rto, $task, $this->taskStage('IN_PROGRESS'), null);

        $task->refresh();
        $this->assertSame('IN_PROGRESS', $task->stage->code);
        $this->assertNotNull($task->started_at);
        $this->assertSame(Order::STAGE_IN_FULFILMENT, $this->order->fresh()->stage->code);
        $this->assertSame(2, $task->statusHistory()->count());

        app(FulfilmentTaskFlow::class)->move($rto, $task->fresh(['stage', 'type', 'fulfilment.order.stage']), $this->taskStage('COMPLETED'), null);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_other_departments_cannot_work_the_task(): void
    {
        $insurance = $this->crmUser('Insurance Employee', department: 'INSURANCE');

        try {
            app(FulfilmentTaskFlow::class)->move($insurance, $this->task('RTO'), $this->taskStage('IN_PROGRESS'), null);
            $this->fail('Insurance must not work the RTO task.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('not_task_department', $exception->rule);
        }
    }

    public function test_hold_requires_a_remark_and_cancelled_is_not_selectable(): void
    {
        $rto = $this->crmUser('RTO Employee', department: 'RTO');
        $flow = app(FulfilmentTaskFlow::class);
        $task = $this->task('RTO');

        $this->assertNotContains(FulfilmentTask::STAGE_CANCELLED, $flow->targets($task, $rto)->pluck('code'));

        foreach ([['ON_HOLD', null, 'workflow_remark_required'], ['CANCELLED', 'x', 'task_cancel_via_order']] as [$code, $remarks, $rule]) {
            try {
                $flow->move($rto, $this->task('RTO'), $this->taskStage($code), $remarks);
                $this->fail("Moving to {$code} should fail.");
            } catch (BusinessRuleException $exception) {
                $this->assertSame($rule, $exception->rule);
            }
        }
    }

    public function test_not_required_task_cannot_be_worked(): void
    {
        $retail = $this->crmUser('Retail Employee', department: 'RETAIL_FINANCE');
        $task = $this->task('FINANCE');
        $this->assertSame(RequirementState::NotRequired, $task->requirement_state);

        $this->assertCount(0, app(FulfilmentTaskFlow::class)->targets($task, $retail));
        $this->expectException(BusinessRuleException::class);
        app(FulfilmentTaskFlow::class)->move($retail, $task, $this->taskStage('IN_PROGRESS'), null);
    }

    public function test_changing_a_requirement_needs_permission_and_reason_and_updates_documents(): void
    {
        $flow = app(FulfilmentTaskFlow::class);
        $task = $this->task('INSURANCE');

        foreach ([[$this->salesman, 'Customer insures', 'not_allowed'], [$this->manager, ' ', 'reason_required'], [$this->manager, 'x', 'waiver_via_workflow']] as $index => [$user, $reason, $rule]) {
            try {
                $flow->changeRequirement($user, $task, $index === 2 ? RequirementState::Waived : RequirementState::NotRequired, $reason);
                $this->fail('Change should be refused.');
            } catch (BusinessRuleException $exception) {
                $this->assertSame($rule, $exception->rule);
            }
        }

        $flow->changeRequirement($this->manager, $task, RequirementState::NotRequired, 'Customer arranges own insurance');

        $task->refresh();
        $this->assertSame(RequirementState::NotRequired, $task->requirement_state);
        $this->assertFalse($task->blocks_delivery);
        $this->assertSame('Customer arranges own insurance', $task->requirement_remarks);
        $this->assertSame(RequirementState::NotRequired, $this->requirement($this->order, 'INS_POLICY', 'INSURANCE')->requirement_state);
        $this->assertSame(RequirementState::Required, $this->requirement($this->order, 'AADHAAR', 'SALES')->requirement_state);
    }

    public function test_assignment_is_limited_to_the_department_and_flows_to_document_requirements(): void
    {
        $rto = $this->crmUser('RTO Employee', department: 'RTO');
        $colleague = $this->crmUser('RTO Employee', department: 'RTO');
        $flow = app(FulfilmentTaskFlow::class);

        try {
            $flow->assign($rto, $this->task('RTO'), $this->salesman->employee);
            $this->fail('Salesman is not in RTO.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('employee_not_in_department', $exception->rule);
        }

        $flow->assign($rto, $this->task('RTO'), $colleague->employee);

        $this->assertSame($colleague->employee->id, $this->task('RTO')->responsible_employee_id);
        $this->assertSame($colleague->employee->id, $this->requirement($this->order, 'RTO_APPLICATION')->responsible_employee_id);
    }

    public function test_cancelling_an_order_keeps_history_and_closes_open_tasks(): void
    {
        $cancel = app(CancelOrder::class);

        foreach ([[$this->salesman, 'x', 'not_allowed'], [$this->crmUser('Owner'), ' ', 'reason_required']] as [$user, $reason, $rule]) {
            try {
                $cancel->handle($user, $this->order->fresh(), $reason);
                $this->fail('Cancellation should be refused.');
            } catch (BusinessRuleException $exception) {
                $this->assertSame($rule, $exception->rule);
            }
        }

        $owner = $this->crmUser('Owner');
        $cancel->handle($owner, $this->order->fresh(), 'Customer withdrew');

        $order = $this->order->fresh(['stage', 'fulfilment.tasks.stage']);
        $this->assertSame(Order::STAGE_CANCELLED, $order->stage->code);
        $this->assertSame('Customer withdrew', $order->cancellation_reason);
        $this->assertSame(FulfilmentStatus::Cancelled, $order->fulfilment->status);
        $this->assertTrue($order->fulfilment->tasks->every(fn (FulfilmentTask $task) => $task->stage->code === FulfilmentTask::STAGE_CANCELLED));
        $this->assertSame(7, $order->fulfilment->tasks->count());

        $this->expectException(BusinessRuleException::class);
        $cancel->handle($owner, $order, 'again');
    }

    private function task(string $code): FulfilmentTask
    {
        return $this->order->fulfilment->tasks()->whereHas('type', fn ($query) => $query->where('code', $code))
            ->with(['type', 'stage', 'fulfilment.order.stage'])->firstOrFail();
    }

    private function taskStage(string $code): WorkflowStage
    {
        return WorkflowStage::findByCode(WorkflowDefinition::FULFILMENT_TASK, $code);
    }
}
