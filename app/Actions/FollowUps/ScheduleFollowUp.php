<?php

namespace App\Actions\FollowUps;

use App\Exceptions\BusinessRuleException;
use App\Models\Concerns\Followable;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\FollowUp;
use App\Models\LookupValue;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ScheduleFollowUp
{
    /**
     * Follow-up on a department file (e.g. chasing a financer), SRS §70.
     */
    public function forRecord(User $actor, Model&Followable $record, Employee $assignee, string $typeCode, CarbonInterface $dueAt, string $purpose): FollowUp
    {
        $this->assertValid($assignee, $typeCode, $dueAt);

        return $record->followUps()->create([
            'branch_id' => $record->followUpBranchId(),
            'assigned_employee_id' => $assignee->id,
            'type_code' => $typeCode,
            'due_at' => $dueAt,
            'purpose' => $purpose,
        ]);
    }

    public function handle(User $actor, Enquiry $enquiry, Employee $assignee, string $typeCode, CarbonInterface $dueAt, string $purpose): FollowUp
    {
        if ($enquiry->isClosed()) {
            throw new BusinessRuleException(__('Follow-ups cannot be scheduled on a closed enquiry.'), 'enquiry_closed');
        }

        $this->assertValid($assignee, $typeCode, $dueAt);

        return DB::transaction(function () use ($enquiry, $assignee, $typeCode, $dueAt, $purpose): FollowUp {
            $followUp = $enquiry->followUps()->create([
                'branch_id' => $enquiry->branch_id,
                'assigned_employee_id' => $assignee->id,
                'type_code' => $typeCode,
                'due_at' => $dueAt,
                'purpose' => $purpose,
            ]);

            $enquiry->forceFill(['last_activity_at' => now()])->save();

            return $followUp;
        });
    }

    private function assertValid(Employee $assignee, string $typeCode, CarbonInterface $dueAt): void
    {
        if ($dueAt->isPast()) {
            throw new BusinessRuleException(__('The follow-up time must be in the future.'), 'past_due');
        }

        if (! LookupValue::options(LookupValue::FOLLOW_UP_TYPE)->has($typeCode)) {
            throw new BusinessRuleException(__('Choose a follow-up type.'), 'invalid_type');
        }

        if (! $assignee->is_active) {
            throw new BusinessRuleException(__('Choose an active employee.'), 'inactive_employee');
        }
    }
}
