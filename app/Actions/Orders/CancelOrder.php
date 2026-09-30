<?php

namespace App\Actions\Orders;

use App\Actions\Inventory\AllocationFlow;
use App\Enums\FulfilmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\FulfilmentTask;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\DB;

/**
 * Cancels an order with a reason (SRS v6.1 §6–7). Nothing is deleted: the order,
 * its tasks, documents and history stay; open tasks move to Cancelled, allocated units
 * return to stock and the finance file is cancelled.
 */
class CancelOrder
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly AllocationFlow $allocations,
    ) {}

    public function handle(User $actor, Order $order, string $reason): Order
    {
        if (! $actor->can('orders.cancel')) {
            throw new BusinessRuleException(__('You are not allowed to cancel orders.'), 'not_allowed');
        }

        $order->loadMissing(['stage', 'fulfilment.tasks.stage']);

        if ($order->stage->is_final) {
            throw new BusinessRuleException(__('The order is already :stage.', ['stage' => mb_strtolower($order->stage->name)]), 'order_closed');
        }

        if (trim($reason) === '') {
            throw new BusinessRuleException(__('Give a reason for cancelling the order.'), 'reason_required');
        }

        DB::transaction(function () use ($actor, $order, $reason): void {
            $this->workflow->transition($order, 'stage_id', WorkflowStage::findByCode(WorkflowDefinition::ORDER, Order::STAGE_CANCELLED), $actor, $reason, force: true);
            $order->update(['cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancellation_reason' => $reason]);

            $cancelled = WorkflowStage::findByCode(WorkflowDefinition::FULFILMENT_TASK, FulfilmentTask::STAGE_CANCELLED);

            foreach ($order->fulfilment->tasks->reject(fn (FulfilmentTask $task) => $task->stage->is_final) as $task) {
                $this->workflow->transition($task, 'stage_id', $cancelled, $actor, $reason, force: true);
            }

            $order->fulfilment->update(['status' => FulfilmentStatus::Cancelled, 'closed_at' => now()]);

            // Units go back to stock and the finance file closes; the account file stays open for refunds.
            $this->allocations->releaseAll($actor, $order, __('Order cancelled: :reason', ['reason' => $reason]));

            if (($finance = $order->financeFile()->with('stage')->first()) !== null && ! $finance->stage->is_final) {
                $this->workflow->transition($finance, 'stage_id', WorkflowStage::findByCode(WorkflowDefinition::FINANCE, 'CANCELLED'), $actor, $reason, force: true);
            }
        });

        return $order->fresh(['stage']);
    }
}
