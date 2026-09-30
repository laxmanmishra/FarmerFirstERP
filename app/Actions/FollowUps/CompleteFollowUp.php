<?php

namespace App\Actions\FollowUps;

use App\Enums\FollowUpStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Enquiry;
use App\Models\FollowUp;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Completes (or cancels) a follow-up with its outcome, optionally scheduling the next one.
 * Only the assignee, or a manager who can see the team's enquiries, may act on it.
 */
class CompleteFollowUp
{
    public function __construct(private readonly ScheduleFollowUp $schedule) {}

    /**
     * @param  array{type_code: string, due_at: CarbonInterface, purpose: string}|null  $next
     */
    public function handle(User $actor, FollowUp $followUp, string $outcome, ?array $next = null): FollowUp
    {
        $this->assertActionable($actor, $followUp);

        if (trim($outcome) === '') {
            throw new BusinessRuleException(__('Record the outcome of the follow-up.'), 'outcome_required');
        }

        return DB::transaction(function () use ($actor, $followUp, $outcome, $next): FollowUp {
            $followUp->update([
                'status' => FollowUpStatus::Completed,
                'completed_at' => now(),
                'completed_by' => $actor->id,
                'outcome' => $outcome,
            ]);

            if ($next !== null && $followUp->followable instanceof Enquiry) {
                $this->schedule->handle($actor, $followUp->followable, $followUp->assignee, $next['type_code'], $next['due_at'], $next['purpose']);
            }

            return $followUp;
        });
    }

    public function cancel(User $actor, FollowUp $followUp, string $reason): FollowUp
    {
        $this->assertActionable($actor, $followUp);

        if (trim($reason) === '') {
            throw new BusinessRuleException(__('Give a reason for cancelling.'), 'reason_required');
        }

        $followUp->update([
            'status' => FollowUpStatus::Cancelled,
            'completed_at' => now(),
            'completed_by' => $actor->id,
            'outcome' => $reason,
        ]);

        return $followUp;
    }

    private function assertActionable(User $actor, FollowUp $followUp): void
    {
        if ($followUp->status !== FollowUpStatus::Pending) {
            throw new BusinessRuleException(__('This follow-up is already closed.'), 'follow_up_closed');
        }

        $isAssignee = $actor->employee?->id === $followUp->assigned_employee_id;
        $isManager = $actor->canAny(['enquiries.view_team', 'enquiries.view_all'])
            && FollowUp::query()->visibleTo($actor)->whereKey($followUp->id)->exists();

        if (! $isAssignee && ! $isManager) {
            throw new BusinessRuleException(__('Only the assigned employee or their manager can update this follow-up.'), 'not_follow_up_owner');
        }
    }
}
