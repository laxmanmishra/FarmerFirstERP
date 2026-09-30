<?php

namespace App\Actions\Orders;

use App\Enums\FulfilmentStatus;
use App\Enums\RequirementState;
use App\Events\OrderBooked;
use App\Exceptions\BusinessRuleException;
use App\Models\Deal;
use App\Models\FulfilmentTaskType;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\DepartmentFileProvisioner;
use App\Services\DocumentRequirementService;
use App\Services\NumberSeriesService;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\DB;

/**
 * Approved deal → ORDER/BOOKING with a frozen commercial snapshot, its FULFILMENT and the
 * configured department tasks, plus the document requirements (SRS §15, §23).
 *
 * Idempotent: the deal can only ever produce one order (UNIQUE orders.deal_id).
 */
class CreateOrderFromDeal
{
    public function __construct(
        private readonly NumberSeriesService $numbers,
        private readonly WorkflowService $workflow,
        private readonly DocumentRequirementService $requirements,
        private readonly DepartmentFileProvisioner $files,
    ) {}

    public function handle(Deal $deal, User $actor): Order
    {
        if ($deal->load('stage')->stage->code !== Deal::STAGE_APPROVED) {
            throw new BusinessRuleException(__('Only an approved deal can become an order.'), 'deal_not_approved');
        }

        return DB::transaction(function () use ($deal, $actor): Order {
            $existing = Order::query()->where('deal_id', $deal->id)->lockForUpdate()->first();

            if ($existing !== null) {
                return $existing;
            }

            $deal->loadMissing(['items', 'branch']);
            $orderStage = $this->workflow->initialStage(WorkflowDefinition::ORDER);

            $order = Order::create([
                'order_no' => $this->numbers->next('order', $deal->branch),
                'deal_id' => $deal->id,
                'customer_id' => $deal->customer_id,
                'farmer_id' => $deal->farmer_id,
                'branch_id' => $deal->branch_id,
                'primary_salesman_employee_id' => $deal->primary_salesman_employee_id,
                'stage_id' => $orderStage->id,
                'order_date' => today(),
                'order_value' => $deal->deal_value,
                'deal_snapshot' => $deal->commercialSnapshot(),
                ...$deal->only(['gross_total', 'discount_total', 'tax_total', 'charges_total', 'exchange_value', 'finance_required',
                    'finance_amount', 'customer_contribution', 'booking_amount', 'expected_delivery_date', 'rto_required',
                    'insurance_required', 'pdi_required', 'remarks']),
            ]);

            foreach ($deal->items as $item) {
                $order->items()->create($item->commercialAttributes());
            }

            $this->workflow->recordInitial($order, $orderStage, $actor);

            $fulfilment = $order->fulfilment()->create([
                'fulfilment_no' => $this->numbers->next('fulfilment', $deal->branch),
                'status' => FulfilmentStatus::Open,
            ]);

            $taskStage = $this->workflow->initialStage(WorkflowDefinition::FULFILMENT_TASK);

            foreach (FulfilmentTaskType::query()->active()->orderBy('sort_order')->get() as $type) {
                $applies = $type->condition->appliesTo($order);

                $task = $fulfilment->tasks()->create([
                    'fulfilment_task_type_id' => $type->id,
                    'department_id' => $type->department_id,
                    'requirement_state' => $applies ? RequirementState::Required : RequirementState::NotRequired,
                    'stage_id' => $taskStage->id,
                    'blocks_delivery' => $applies && $type->blocks_delivery,
                    'due_date' => $order->expected_delivery_date,
                ]);

                $this->workflow->recordInitial($task, $taskStage, $actor);
            }

            $this->files->provision($order, $actor);
            $this->requirements->sync($order, $actor);

            OrderBooked::dispatch($order, $actor);

            return $order;
        });
    }
}
