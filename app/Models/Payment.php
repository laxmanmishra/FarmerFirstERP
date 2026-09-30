<?php

namespace App\Models;

use App\Enums\PayerType;
use App\Enums\PaymentKind;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * One money movement on an account file (SRS §96). The amount never changes after it is
 * recorded; corrections are reversal entries that reference the original (INV-06).
 */
#[Fillable([
    'payment_no', 'account_file_id', 'kind', 'payer_type', 'mode', 'amount', 'reference_no', 'instrument_date', 'bank_name', 'received_on',
    'status', 'status_reason', 'reverses_payment_id', 'refund_request_id', 'remarks', 'recorded_by', 'verified_by', 'verified_at', 'cleared_by', 'cleared_at',
])]
class Payment extends Model
{
    use Auditable, HasUserstamps;

    protected string $auditModule = 'accounts';

    protected static function booted(): void
    {
        static::updating(function (Payment $payment): void {
            if ($payment->isDirty(['amount', 'account_file_id', 'kind', 'payer_type', 'mode', 'reverses_payment_id'])) {
                throw new LogicException('Payment amounts and parties are immutable; record a reversal instead.');
            }
        });
        static::deleting(fn () => throw new LogicException('Payments are never deleted.'));
    }

    protected function casts(): array
    {
        return [
            'kind' => PaymentKind::class,
            'payer_type' => PayerType::class,
            'mode' => PaymentMode::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'instrument_date' => 'date',
            'received_on' => 'date',
            'verified_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AccountFile, $this>
     */
    public function accountFile(): BelongsTo
    {
        return $this->belongsTo(AccountFile::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'reverses_payment_id');
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(Payment::class, 'reverses_payment_id');
    }

    /**
     * @return HasOne<Receipt, $this>
     */
    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
