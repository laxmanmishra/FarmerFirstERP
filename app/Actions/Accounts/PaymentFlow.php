<?php

namespace App\Actions\Accounts;

use App\Enums\PayerType;
use App\Enums\PaymentKind;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AccountFile;
use App\Models\Payment;
use App\Models\User;
use App\Services\NumberSeriesService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Payments on an account file (SRS §96–103): record → verify (issues the receipt) →
 * clear. Rejections, bounces and reversals keep the original row; a reversal is a new
 * negative entry that references it (INV-06). Recorder and verifier must differ.
 */
class PaymentFlow
{
    public function __construct(
        private readonly NumberSeriesService $numbers,
        private readonly AccountFileFlow $files,
    ) {}

    /**
     * @param  array{payer_type: string, mode: string, amount: string, reference_no: ?string, instrument_date: ?string, bank_name: ?string, received_on: string, remarks: ?string}  $data
     */
    public function record(User $actor, AccountFile $file, array $data): Payment
    {
        $this->assertCan($actor, 'accounts.record_payment');
        $file->loadMissing(['order', 'stage', 'branch']);

        if ($file->order->isCancelled()) {
            throw new BusinessRuleException(__('The order is cancelled; record a refund instead.'), 'order_cancelled');
        }

        $payer = PayerType::from($data['payer_type']);
        $mode = PaymentMode::from($data['mode']);
        $amount = Money::normalise($data['amount']);

        if (Money::compare($amount, 0) <= 0) {
            throw new BusinessRuleException(__('The amount must be more than zero.'), 'invalid_amount');
        }

        if (($mode === PaymentMode::FinanceDisbursement) !== ($payer === PayerType::Financer)) {
            throw new BusinessRuleException(__('Finance disbursements come from the financer, and financer money is recorded as a disbursement.'), 'mode_payer_mismatch');
        }

        if ($payer === PayerType::Financer && ! $file->order->finance_required) {
            throw new BusinessRuleException(__('This order has no finance.'), 'no_finance');
        }

        if ($mode->needsReference() && trim((string) $data['reference_no']) === '') {
            throw new BusinessRuleException(__('Enter the :mode reference number.', ['mode' => $mode->label()]), 'reference_required');
        }

        if ($data['received_on'] > today()->toDateString()) {
            throw new BusinessRuleException(__('The received date cannot be in the future.'), 'future_date');
        }

        return DB::transaction(fn (): Payment => $file->payments()->create([
            'payment_no' => $this->numbers->next('payment', $file->branch),
            'kind' => PaymentKind::Receipt,
            'payer_type' => $payer,
            'mode' => $mode,
            'amount' => $amount,
            'reference_no' => $data['reference_no'] ?: null,
            'instrument_date' => $data['instrument_date'] ?: null,
            'bank_name' => $data['bank_name'] ?: null,
            'received_on' => $data['received_on'],
            'status' => PaymentStatus::PendingVerification,
            'remarks' => $data['remarks'] ?: null,
            'recorded_by' => $actor->id,
        ]));
    }

    /**
     * Confirms the money was really received and issues the receipt.
     */
    public function verify(User $actor, Payment $payment): Payment
    {
        $this->assertCan($actor, 'accounts.verify_payment');
        $this->assertStatus($payment, [PaymentStatus::PendingVerification]);

        if ($payment->recorded_by === $actor->id) {
            throw new BusinessRuleException(__('You recorded this payment, so someone else must verify it.'), 'self_verification');
        }

        $payment->loadMissing('accountFile.branch');

        DB::transaction(function () use ($actor, $payment): void {
            $payment->update(['status' => PaymentStatus::Verified, 'verified_by' => $actor->id, 'verified_at' => now()]);
            $payment->receipt()->create([
                'receipt_no' => $this->numbers->next('receipt', $payment->accountFile->branch),
                'account_file_id' => $payment->account_file_id,
                'amount' => $payment->amount,
                'issued_at' => now(),
                'issued_by' => $actor->id,
            ]);
        });

        return $payment;
    }

    /**
     * The money is in the bank (cheque realised, transfer credited, cash banked).
     */
    public function clear(User $actor, Payment $payment): Payment
    {
        $this->assertCan($actor, 'accounts.clear_payment');
        $this->assertStatus($payment, [PaymentStatus::Verified]);

        $payment->update(['status' => PaymentStatus::Cleared, 'cleared_by' => $actor->id, 'cleared_at' => now()]);

        return $payment;
    }

    public function reject(User $actor, Payment $payment, string $reason): Payment
    {
        $this->assertCan($actor, 'accounts.verify_payment');
        $this->assertStatus($payment, [PaymentStatus::PendingVerification]);
        $this->assertReason($reason);

        $payment->update(['status' => PaymentStatus::Rejected, 'status_reason' => $reason]);

        return $payment;
    }

    /**
     * Cheque / DD bounced after it was accepted: the receipt is cancelled and the file
     * reopens if it was already cleared.
     */
    public function markReturned(User $actor, Payment $payment, string $reason): Payment
    {
        $this->assertCan($actor, 'accounts.clear_payment');
        $this->assertStatus($payment, [PaymentStatus::Verified, PaymentStatus::Cleared]);
        $this->assertReason($reason);

        if (! $payment->mode->canBounce()) {
            throw new BusinessRuleException(__(':mode payments cannot bounce; reverse the entry instead.', ['mode' => $payment->mode->label()]), 'cannot_bounce');
        }

        DB::transaction(function () use ($actor, $payment, $reason): void {
            $payment->update(['status' => PaymentStatus::Returned, 'status_reason' => $reason]);
            $payment->receipt?->update(['cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $this->files->reopenIfShort($actor, $payment->accountFile->fresh(), __('Payment :no returned: :reason', ['no' => $payment->payment_no, 'reason' => $reason]));
        });

        return $payment;
    }

    /**
     * Corrects a cleared payment that was recorded in error: a new negative entry
     * references the original, which is kept and marked reversed.
     */
    public function reverse(User $actor, Payment $payment, string $reason): Payment
    {
        $this->assertCan($actor, 'accounts.reverse');
        $this->assertStatus($payment, [PaymentStatus::Cleared]);
        $this->assertReason($reason);

        if ($payment->kind !== PaymentKind::Receipt) {
            throw new BusinessRuleException(__('Only receipts can be reversed.'), 'not_reversible');
        }

        if ($payment->recorded_by === $actor->id) {
            throw new BusinessRuleException(__('You recorded this payment, so someone else must reverse it.'), 'self_reversal');
        }

        $payment->loadMissing(['accountFile.branch', 'receipt']);

        return DB::transaction(function () use ($actor, $payment, $reason): Payment {
            $reversal = $payment->accountFile->payments()->create([
                'payment_no' => $this->numbers->next('payment', $payment->accountFile->branch),
                'kind' => PaymentKind::Reversal,
                'payer_type' => $payment->payer_type,
                'mode' => $payment->mode,
                'amount' => Money::sub(0, $payment->amount),
                'reference_no' => $payment->payment_no,
                'received_on' => today(),
                'status' => PaymentStatus::Cleared,
                'status_reason' => $reason,
                'reverses_payment_id' => $payment->id,
                'recorded_by' => $actor->id,
                'cleared_by' => $actor->id,
                'cleared_at' => now(),
            ]);

            $payment->update(['status' => PaymentStatus::Reversed, 'status_reason' => $reason]);
            $payment->receipt?->update(['cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $this->files->reopenIfShort($actor, $payment->accountFile->fresh(), __('Payment :no reversed: :reason', ['no' => $payment->payment_no, 'reason' => $reason]));

            return $reversal;
        });
    }

    private function assertCan(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new BusinessRuleException(__('You are not allowed to do this.'), 'not_allowed');
        }
    }

    /**
     * @param  list<PaymentStatus>  $allowed
     */
    private function assertStatus(Payment $payment, array $allowed): void
    {
        if (! in_array($payment->status, $allowed, true)) {
            throw new BusinessRuleException(__('Payment :no is :status.', ['no' => $payment->payment_no, 'status' => mb_strtolower($payment->status->label())]), 'payment_status');
        }
    }

    private function assertReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new BusinessRuleException(__('Give a reason.'), 'reason_required');
        }
    }
}
