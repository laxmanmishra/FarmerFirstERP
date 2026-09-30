<?php

namespace App\Actions\Finance;

use App\Actions\Fulfilment\FulfilmentTaskFlow;
use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\FinanceFile;
use App\Models\Financer;
use App\Models\FinancerContact;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Services\WorkflowService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Retail & Finance work on a finance file (SRS §65–73): financer and loan details, status
 * as reported by the financer, and the responsible employee. The order's finance task
 * follows the file's status.
 */
class FinanceFileFlow
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly FulfilmentTaskFlow $tasks,
    ) {}

    /**
     * @param  array{financer_id: ?int, financer_contact_id: ?int, sanctioned_amount: ?string, down_payment: ?string, tenure_months: ?int, interest_rate: ?string, emi_amount: ?string, loan_account_no: ?string, do_number: ?string, do_date: ?string, do_amount: ?string, do_valid_until: ?string, disbursed_amount: ?string, disbursed_on: ?string, remarks: ?string}  $details
     */
    public function updateDetails(User $actor, FinanceFile $file, array $details): FinanceFile
    {
        $this->assertOpen($actor, $file, 'finance.update');

        $financer = $details['financer_id'] ? Financer::query()->find($details['financer_id']) : null;

        if ($details['financer_id'] && ($financer === null || (! $financer->is_active && $financer->id !== $file->financer_id))) {
            throw new BusinessRuleException(__('Choose an active financer.'), 'financer_inactive');
        }

        if ($details['financer_contact_id'] && ! FinancerContact::query()->whereKey($details['financer_contact_id'])->where('financer_id', $financer?->id)->exists()) {
            throw new BusinessRuleException(__('The contact does not belong to the chosen financer.'), 'contact_mismatch');
        }

        foreach (['sanctioned_amount', 'do_amount', 'disbursed_amount'] as $field) {
            if ($details[$field] !== null && Money::compare($details[$field], $file->order->order_value) > 0) {
                throw new BusinessRuleException(__('Amounts cannot exceed the order value.'), 'amount_exceeds_order');
            }
        }

        if ($details['do_date'] && $details['do_valid_until'] && $details['do_valid_until'] < $details['do_date']) {
            throw new BusinessRuleException(__('The DO validity must end on or after the DO date.'), 'do_validity');
        }

        $file->update($details);

        return $file;
    }

    public function move(User $actor, FinanceFile $file, WorkflowStage $to, ?string $remarks): FinanceFile
    {
        $this->assertOpen($actor, $file, 'finance.update');

        if ($to->is_system) {
            throw new BusinessRuleException(__('":stage" is set by the system.', ['stage' => $to->name]), 'system_stage');
        }

        if ($to->is_completion && ($file->financer_id === null || $file->sanctioned_amount === null)) {
            throw new BusinessRuleException(__('Record the financer and the sanctioned amount before completing finance.'), 'finance_incomplete');
        }

        DB::transaction(function () use ($actor, $file, $to, $remarks): void {
            $this->workflow->transition($file, 'stage_id', $to, $actor, $remarks);
            $this->tasks->follow($actor, $file->task, $to, $remarks);
        });

        return $file->fresh(['stage']);
    }

    public function assign(User $actor, FinanceFile $file, ?Employee $employee): FinanceFile
    {
        $this->assertOpen($actor, $file, 'finance.assign');

        if ($employee !== null && (! $employee->is_active || ! $employee->departments()->where('code', 'RETAIL_FINANCE')->exists())) {
            throw new BusinessRuleException(__(':name is not an active Retail & Finance employee.', ['name' => $employee->name]), 'employee_not_in_department');
        }

        DB::transaction(function () use ($file, $employee): void {
            $file->update(['responsible_employee_id' => $employee?->id]);
            $file->task?->update(['responsible_employee_id' => $employee?->id]);
            $file->task?->documentRequirements()->update(['responsible_employee_id' => $employee?->id]);
        });

        return $file;
    }

    private function assertOpen(User $actor, FinanceFile $file, string $permission): void
    {
        $file->loadMissing(['stage', 'order', 'task']);

        if (! $actor->can($permission)) {
            throw new BusinessRuleException(__('You are not allowed to change finance files.'), 'not_allowed');
        }

        if ($file->stage->is_final || $file->order->isCancelled()) {
            throw new BusinessRuleException(__('This finance file is closed.'), 'file_closed');
        }
    }
}
