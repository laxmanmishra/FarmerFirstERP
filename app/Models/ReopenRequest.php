<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Salesman/telecaller request to reopen a closed enquiry; decided by Manager/Owner (SRS §11).
 */
#[Fillable(['enquiry_id', 'requested_by', 'reason', 'status', 'decided_by', 'decided_at', 'decision_remarks'])]
class ReopenRequest extends Model
{
    use Auditable;

    protected string $auditModule = 'enquiries';

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['status' => ApprovalStatus::class, 'decided_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Enquiry, $this>
     */
    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
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
