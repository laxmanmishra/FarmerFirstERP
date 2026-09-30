<?php

namespace App\Actions\Enquiries;

use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\User;
use App\Notifications\EnquiryAssigned;
use Illuminate\Support\Facades\DB;

/**
 * Manager/Owner assignment and reassignment with history (SRS §32, §33).
 */
class AssignEnquiry
{
    public function handle(User $actor, Enquiry $enquiry, ?Employee $to, string $reason): Enquiry
    {
        if ($enquiry->isClosed()) {
            throw new BusinessRuleException(__('Closed enquiries cannot be reassigned.'), 'enquiry_closed');
        }

        if ($to !== null && ! $to->is_active) {
            throw new BusinessRuleException(__('Choose an active employee.'), 'inactive_employee');
        }

        if ($enquiry->assigned_employee_id === $to?->id) {
            throw new BusinessRuleException(__('The enquiry is already assigned to this employee.'), 'same_assignee');
        }

        DB::transaction(function () use ($actor, $enquiry, $to, $reason): void {
            $enquiry->assignments()->create([
                'from_employee_id' => $enquiry->assigned_employee_id,
                'to_employee_id' => $to?->id,
                'reason' => $reason,
                'assigned_by' => $actor->id,
            ]);

            $enquiry->update(['assigned_employee_id' => $to?->id, 'last_activity_at' => now()]);
        });

        if ($to?->user !== null && $to->user_id !== $actor->id) {
            $to->user->notify(new EnquiryAssigned($enquiry));
        }

        return $enquiry;
    }
}
