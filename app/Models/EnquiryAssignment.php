<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assignment history row; never updated (SRS §33).
 */
#[Fillable(['enquiry_id', 'from_employee_id', 'to_employee_id', 'reason', 'assigned_by'])]
class EnquiryAssignment extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function fromEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'from_employee_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function toEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_employee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
