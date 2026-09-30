<?php

namespace App\Actions\Accounts;

use App\Actions\Fulfilment\FulfilmentTaskFlow;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AccountFile;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Services\WorkflowService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Accounts file status (SRS §94). Completion is refused while the cleared money is short of
 * the receivable or payments are still uncleared; the order's accounts task follows.
 */
class AccountFileFlow
{
    public const STAGE_SHORT = 'PAYMENT_SHORT';

    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly FulfilmentTaskFlow $tasks,
    ) {}

    public function move(User $actor, AccountFile $file, WorkflowStage $to, ?string $remarks): AccountFile
    {
        $file->loadMissing(['stage', 'order', 'task', 'payments']);

        if (! $actor->can('accounts.clear_payment')) {
            throw new BusinessRuleException(__('You are not allowed to change account files.'), 'not_allowed');
        }

        if ($file->stage->is_final) {
            throw new BusinessRuleException(__('This account file is closed.'), 'file_closed');
        }

        if ($to->is_completion) {
            if (Money::compare($file->balance(), 0) > 0) {
                throw new BusinessRuleException(__('₹:amount is still to be cleared.', ['amount' => Money::format($file->balance())]), 'balance_outstanding');
            }

            if ($file->payments->contains(fn ($payment) => in_array($payment->status, [PaymentStatus::PendingVerification, PaymentStatus::Verified], true))) {
                throw new BusinessRuleException(__('Some payments are not cleared yet.'), 'payments_uncleared');
            }
        }

        DB::transaction(function () use ($actor, $file, $to, $remarks): void {
            $this->workflow->transition($file, 'stage_id', $to, $actor, $remarks);
            $this->tasks->follow($actor, $file->task, $to, $remarks);
        });

        return $file->fresh(['stage']);
    }

    /**
     * A bounce or reversal after clearance puts a completed file back to Payment short.
     */
    public function reopenIfShort(User $actor, AccountFile $file, string $reason): void
    {
        $file->load(['stage', 'task', 'payments']);

        if (! $file->stage->is_completion || Money::compare($file->balance(), 0) <= 0) {
            return;
        }

        $short = WorkflowStage::findByCode(WorkflowDefinition::ACCOUNTS, self::STAGE_SHORT);
        $this->workflow->transition($file, 'stage_id', $short, $actor, $reason, force: true);
        $this->tasks->follow($actor, $file->task, $short, $reason);
    }

    public function assign(User $actor, AccountFile $file, ?Employee $employee): AccountFile
    {
        if (! $actor->can('accounts.clear_payment')) {
            throw new BusinessRuleException(__('You are not allowed to change account files.'), 'not_allowed');
        }

        if ($employee !== null && (! $employee->is_active || ! $employee->departments()->where('code', 'ACCOUNTS')->exists())) {
            throw new BusinessRuleException(__(':name is not an active Accounts employee.', ['name' => $employee->name]), 'employee_not_in_department');
        }

        $file->loadMissing('task');

        DB::transaction(function () use ($file, $employee): void {
            $file->update(['responsible_employee_id' => $employee?->id]);
            $file->task?->update(['responsible_employee_id' => $employee?->id]);
            $file->task?->documentRequirements()->update(['responsible_employee_id' => $employee?->id]);
        });

        return $file;
    }
}
