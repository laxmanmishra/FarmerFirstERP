<?php

namespace App\Models;

use App\Enums\RefundStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Refund with approval (SRS §106–109): requested by Accounts, approved by someone else
 * holding accounts.approve_refund, then paid out as a negative payment entry.
 */
#[Fillable(['refund_no', 'account_file_id', 'amount', 'reason', 'status', 'requested_by', 'decided_by', 'decided_at', 'decision_remarks', 'paid_at'])]
class RefundRequest extends Model
{
    use Auditable, HasUserstamps;

    protected string $auditModule = 'accounts';

    protected function casts(): array
    {
        return ['status' => RefundStatus::class, 'amount' => 'decimal:2', 'decided_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<AccountFile, $this>
     */
    public function accountFile(): BelongsTo
    {
        return $this->belongsTo(AccountFile::class);
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
