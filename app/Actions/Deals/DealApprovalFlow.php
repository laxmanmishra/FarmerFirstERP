<?php

namespace App\Actions\Deals;

use App\Actions\Orders\CreateOrderFromDeal;
use App\Enums\DealDecision;
use App\Events\DealApproved;
use App\Exceptions\BusinessRuleException;
use App\Models\Deal;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Notifications\DealDecided;
use App\Notifications\DealSubmitted;
use App\Services\DealReadiness;
use App\Services\WorkflowService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Deal terms, Deal Ready submission and Manager/Owner decision (SRS §14).
 *
 * Separation of duties: the submitter and the primary salesman cannot decide.
 * Every submission and decision stores an immutable snapshot of the commercial values.
 * Approval books the ORDER in the same transaction (SRS §15).
 */
class DealApprovalFlow
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly DealReadiness $readiness,
        private readonly CreateOrderFromDeal $createOrder,
    ) {}

    /**
     * @param  array{expected_delivery_date: ?string, booking_amount: string|int|float|null, finance_required: bool, finance_amount: string|int|float|null, rto_required: bool, insurance_required: bool, pdi_required: bool, remarks: ?string}  $terms
     */
    public function updateTerms(Deal $deal, array $terms): Deal
    {
        $this->assertEditable($deal);

        $finance = $terms['finance_required'] ? Money::normalise($terms['finance_amount']) : '0.00';
        $booking = Money::normalise($terms['booking_amount']);

        if (Money::compare($finance, $deal->deal_value) > 0) {
            throw new BusinessRuleException(__('The finance amount cannot exceed the deal value.'), 'finance_exceeds_value');
        }

        $contribution = Money::sub($deal->deal_value, $finance);

        if (Money::compare($booking, $contribution) > 0) {
            throw new BusinessRuleException(__('The booking amount cannot exceed the customer contribution.'), 'booking_exceeds_contribution');
        }

        $deal->update([
            'expected_delivery_date' => $terms['expected_delivery_date'] ?: null,
            'booking_amount' => $booking,
            'finance_required' => $terms['finance_required'],
            'finance_amount' => $finance,
            'customer_contribution' => $contribution,
            'rto_required' => $terms['rto_required'],
            'insurance_required' => $terms['insurance_required'],
            'pdi_required' => $terms['pdi_required'],
            'remarks' => $terms['remarks'],
        ]);

        return $deal;
    }

    public function submit(User $actor, Deal $deal): Deal
    {
        $this->assertEditable($deal);

        if (($missing = $this->readiness->missing($deal)) !== []) {
            throw new BusinessRuleException(implode(' ', $missing), 'deal_not_ready', ['missing' => $missing]);
        }

        DB::transaction(function () use ($actor, $deal): void {
            $this->workflow->transition($deal, 'stage_id', $this->stage(Deal::STAGE_READY), $actor, force: true);
            $deal->update(['submitted_at' => now(), 'submitted_by' => $actor->id]);
            $this->record($deal, DealDecision::Submitted, $actor, null);
        });

        $approvers = User::withPermissionInBranch('deals.approve', $deal->branch_id, $actor)
            ->filter(fn (User $user) => $user->employee?->id !== $deal->primary_salesman_employee_id);
        Notification::send($approvers, new DealSubmitted($deal));

        return $deal;
    }

    public function decide(User $actor, Deal $deal, DealDecision $decision, ?string $remarks): Deal
    {
        if ($decision === DealDecision::Submitted) {
            throw new BusinessRuleException(__('Choose approve, send back or reject.'), 'invalid_decision');
        }

        if (! $deal->isAwaitingApproval()) {
            throw new BusinessRuleException(__('This deal is not awaiting approval.'), 'deal_not_ready');
        }

        if (! $actor->can('deals.approve')) {
            throw new BusinessRuleException(__('You are not allowed to approve deals.'), 'not_approver');
        }

        if ($actor->id === $deal->submitted_by || ($actor->employee !== null && $actor->employee->id === $deal->primary_salesman_employee_id)) {
            throw new BusinessRuleException(__('You cannot decide a deal you submitted or own as salesman.'), 'self_approval');
        }

        if ($decision !== DealDecision::Approved && trim((string) $remarks) === '') {
            throw new BusinessRuleException(__('Give a reason when sending back or rejecting a deal.'), 'reason_required');
        }

        DB::transaction(function () use ($actor, $deal, $decision, $remarks): void {
            $target = match ($decision) {
                DealDecision::Approved => Deal::STAGE_APPROVED,
                DealDecision::SentBack => Deal::STAGE_SENT_BACK,
                DealDecision::Rejected => Deal::STAGE_REJECTED,
            };

            $this->workflow->transition($deal, 'stage_id', $this->stage($target), $actor, $remarks, force: true);

            $deal->update(match ($decision) {
                DealDecision::Approved => ['approved_at' => now(), 'approved_by' => $actor->id],
                DealDecision::Rejected => ['closed_at' => now()],
                default => [],
            });

            if ($decision === DealDecision::Approved && ($exchange = $deal->enquiry->exchangeTractor) !== null) {
                // The approved deal fixes the internally approved exchange value (SRS v6.1 §4).
                $exchange->update(['approved_exchange_value' => $deal->exchange_value]);
            }

            $this->record($deal, $decision, $actor, $remarks);

            if ($decision === DealDecision::Approved) {
                // Approval and booking succeed or fail together (docs/WORKFLOW.md §3.4).
                $this->createOrder->handle($deal, $actor);
            }
        });

        $deal->refresh();

        if ($decision === DealDecision::Approved) {
            DealApproved::dispatch($deal, $actor);
        }

        $recipients = collect([$deal->submitted_by ? User::query()->find($deal->submitted_by) : null, $deal->primarySalesman?->user])
            ->filter()->unique('id')->reject(fn (User $user) => $user->is($actor));
        Notification::send($recipients, new DealDecided($deal, $decision, $remarks));

        return $deal;
    }

    private function record(Deal $deal, DealDecision $decision, User $actor, ?string $remarks): void
    {
        $deal->approvals()->create([
            'action' => $decision,
            'user_id' => $actor->id,
            'remarks' => $remarks,
            'snapshot' => $deal->fresh('items')->commercialSnapshot(),
        ]);
    }

    private function assertEditable(Deal $deal): void
    {
        if (! $deal->loadMissing('stage')->isEditable()) {
            throw new BusinessRuleException(__('The deal can only change while it is a draft or sent back.'), 'deal_locked');
        }
    }

    private function stage(string $code): WorkflowStage
    {
        return WorkflowStage::findByCode(WorkflowDefinition::DEAL, $code);
    }
}
