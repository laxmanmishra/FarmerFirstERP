<?php

namespace App\Actions\Accounts;

use App\Enums\PayerType;
use App\Enums\PaymentKind;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AccountFile;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\NumberSeriesService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Refunds (SRS §106–109): request → approve / reject by someone else holding
 * accounts.approve_refund → paid as a negative, cleared payment entry.
 */
class RefundFlow
{
    public function __construct(private readonly NumberSeriesService $numbers) {}

    public function request(User $actor, AccountFile $file, string $amount, string $reason): RefundRequest
    {
        if (! $actor->can('accounts.record_payment')) {
            throw new BusinessRuleException(__('You are not allowed to request refunds.'), 'not_allowed');
        }

        $file->loadMissing(['order', 'payments', 'refunds', 'branch']);
        $amount = Money::normalise($amount);

        if (Money::compare($amount, 0) <= 0 || Money::compare($amount, $file->refundable()) > 0) {
            throw new BusinessRuleException(__('At most ₹:amount can be refunded.', ['amount' => Money::format($file->refundable())]), 'refund_exceeds_available');
        }

        if (trim($reason) === '') {
            throw new BusinessRuleException(__('Give the reason for the refund.'), 'reason_required');
        }

        return $file->refunds()->create([
            'refund_no' => $this->numbers->next('refund', $file->branch),
            'amount' => $amount,
            'reason' => $reason,
            'status' => RefundStatus::Requested,
            'requested_by' => $actor->id,
        ]);
    }

    public function decide(User $actor, RefundRequest $refund, bool $approve, ?string $remarks): RefundRequest
    {
        if (! $actor->can('accounts.approve_refund')) {
            throw new BusinessRuleException(__('You are not allowed to approve refunds.'), 'not_approver');
        }

        if ($refund->status !== RefundStatus::Requested) {
            throw new BusinessRuleException(__('This refund was already decided.'), 'refund_decided');
        }

        if ($refund->requested_by === $actor->id) {
            throw new BusinessRuleException(__('You requested this refund, so someone else must decide it.'), 'self_approval');
        }

        if (! $approve && trim((string) $remarks) === '') {
            throw new BusinessRuleException(__('Give a reason for rejecting the refund.'), 'reason_required');
        }

        $refund->update([
            'status' => $approve ? RefundStatus::Approved : RefundStatus::Rejected,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_remarks' => $remarks,
        ]);

        return $refund;
    }

    public function pay(User $actor, RefundRequest $refund, PaymentMode $mode, ?string $reference): Payment
    {
        if (! $actor->can('accounts.clear_payment')) {
            throw new BusinessRuleException(__('You are not allowed to pay refunds.'), 'not_allowed');
        }

        if ($refund->status !== RefundStatus::Approved) {
            throw new BusinessRuleException(__('Only approved refunds can be paid.'), 'refund_not_approved');
        }

        if ($mode === PaymentMode::FinanceDisbursement || ($mode->needsReference() && trim((string) $reference) === '')) {
            throw new BusinessRuleException(__('Choose how the refund was paid and enter its reference.'), 'reference_required');
        }

        $refund->loadMissing('accountFile.branch');

        return DB::transaction(function () use ($actor, $refund, $mode, $reference): Payment {
            $payment = $refund->accountFile->payments()->create([
                'payment_no' => $this->numbers->next('payment', $refund->accountFile->branch),
                'kind' => PaymentKind::Refund,
                'payer_type' => PayerType::Customer,
                'mode' => $mode,
                'amount' => Money::sub(0, $refund->amount),
                'reference_no' => $reference ?: null,
                'received_on' => today(),
                'status' => PaymentStatus::Cleared,
                'refund_request_id' => $refund->id,
                'remarks' => $refund->reason,
                'recorded_by' => $actor->id,
                'cleared_by' => $actor->id,
                'cleared_at' => now(),
            ]);

            $refund->update(['status' => RefundStatus::Paid, 'paid_at' => now()]);

            return $payment;
        });
    }
}
