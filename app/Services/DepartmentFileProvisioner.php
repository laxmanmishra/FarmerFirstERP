<?php

namespace App\Services;

use App\Models\AccountFile;
use App\Models\FinanceFile;
use App\Models\FulfilmentTask;
use App\Models\FulfilmentTaskType;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkflowDefinition;

/**
 * Opens the department files an order needs (SRS §57, §91): a finance file when the
 * finance task applies, an account file for every order. Idempotent — called on
 * booking and again whenever a task's requirement changes. Files are never deleted.
 */
class DepartmentFileProvisioner
{
    public function __construct(
        private readonly NumberSeriesService $numbers,
        private readonly WorkflowService $workflow,
    ) {}

    public function provision(Order $order, User $actor): void
    {
        $order->loadMissing(['fulfilment.tasks.type', 'branch', 'financeFile', 'accountFile']);

        foreach ($order->fulfilment?->tasks ?? [] as $task) {
            if (! $task->requirement_state->isApplicable()) {
                continue;
            }

            match ($task->type->driven_by) {
                FulfilmentTaskType::DRIVEN_BY_FINANCE_FILE => $order->financeFile ?? $this->openFinanceFile($order, $task, $actor),
                FulfilmentTaskType::DRIVEN_BY_ACCOUNT_FILE => $order->accountFile ?? $this->openAccountFile($order, $task, $actor),
                default => null,
            };
        }
    }

    private function openFinanceFile(Order $order, FulfilmentTask $task, User $actor): FinanceFile
    {
        $stage = $this->workflow->initialStage(WorkflowDefinition::FINANCE);

        $file = FinanceFile::create([
            'file_no' => $this->numbers->next('finance_file', $order->branch),
            'order_id' => $order->id,
            'fulfilment_task_id' => $task->id,
            'branch_id' => $order->branch_id,
            'stage_id' => $stage->id,
            'loan_amount' => $order->finance_amount,
            'responsible_employee_id' => $task->responsible_employee_id,
        ]);
        $this->workflow->recordInitial($file, $stage, $actor);
        $order->setRelation('financeFile', $file);

        return $file;
    }

    private function openAccountFile(Order $order, FulfilmentTask $task, User $actor): AccountFile
    {
        $stage = $this->workflow->initialStage(WorkflowDefinition::ACCOUNTS);

        $file = AccountFile::create([
            'file_no' => $this->numbers->next('account_file', $order->branch),
            'order_id' => $order->id,
            'fulfilment_task_id' => $task->id,
            'branch_id' => $order->branch_id,
            'stage_id' => $stage->id,
            'receivable_amount' => $order->order_value,
            'customer_share' => $order->customer_contribution,
            'finance_share' => $order->finance_amount,
            'responsible_employee_id' => $task->responsible_employee_id,
        ]);
        $this->workflow->recordInitial($file, $stage, $actor);
        $order->setRelation('accountFile', $file);

        return $file;
    }
}
