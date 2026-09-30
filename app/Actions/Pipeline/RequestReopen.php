<?php

namespace App\Actions\Pipeline;

use App\Enums\ApprovalStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Enquiry;
use App\Models\ReopenRequest;
use App\Models\User;
use App\Notifications\ReopenRequested;
use Illuminate\Support\Facades\Notification;

/**
 * Salesman/telecaller request to reopen a closed enquiry (SRS §11).
 * Notifies users holding enquiries.reopen in the enquiry's branch.
 */
class RequestReopen
{
    public function handle(User $actor, Enquiry $enquiry, string $reason): ReopenRequest
    {
        $enquiry->loadMissing('pipelineStage');

        if (! $enquiry->isClosed()) {
            throw new BusinessRuleException(__('This enquiry is already open.'), 'enquiry_open');
        }

        if ($enquiry->pipelineStage?->is_completion) {
            throw new BusinessRuleException(__('Won enquiries cannot be reopened; continue with the customer and deal.'), 'won_not_reopenable');
        }

        if ($enquiry->reopenRequests()->where('status', ApprovalStatus::Pending)->exists()) {
            throw new BusinessRuleException(__('A reopen request for this enquiry is already awaiting a decision.'), 'reopen_request_pending');
        }

        $request = $enquiry->reopenRequests()->create([
            'requested_by' => $actor->id,
            'reason' => $reason,
            'status' => ApprovalStatus::Pending,
        ]);

        $approvers = User::withPermissionInBranch('enquiries.reopen', $enquiry->branch_id, $actor);

        Notification::send($approvers, new ReopenRequested($request));

        return $request;
    }
}
