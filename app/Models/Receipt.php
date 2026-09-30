<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Customer receipt for a verified payment (SRS §99). Cancelled, never deleted, when the
 * payment later bounces or is reversed.
 */
#[Fillable(['receipt_no', 'payment_id', 'account_file_id', 'amount', 'issued_at', 'issued_by', 'cancelled_at', 'cancellation_reason'])]
class Receipt extends Model
{
    use Auditable;

    protected string $auditModule = 'accounts';

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'issued_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<AccountFile, $this>
     */
    public function accountFile(): BelongsTo
    {
        return $this->belongsTo(AccountFile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
