<?php

namespace App\Actions\Pipeline;

use App\Enums\ApprovalStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ReopenRequest;
use App\Models\User;
use App\Notifications\ReopenDecided;
use Illuminate\Support\Facades\DB;

/**
 * Manager/Owner decision on a reopen request. Separation of duties: the requester
 * can never decide their own request.
 */
class DecideReopenRequest
{
    public function __construct(private readonly ReopenEnquiry $reopen) {}

    public function handle(User $actor, ReopenRequest $request, bool $approve, ?string $remarks): ReopenRequest
    {
        if ($request->status !== ApprovalStatus::Pending) {
            throw new BusinessRuleException(__('This request has already been decided.'), 'already_decided');
        }

        if ($request->requested_by === $actor->id) {
            throw new BusinessRuleException(__('You cannot decide your own reopen request.'), 'self_approval');
        }

        if (! $approve && trim((string) $remarks) === '') {
            throw new BusinessRuleException(__('Give a reason for rejecting the request.'), 'reason_required');
        }

        DB::transaction(function () use ($actor, $request, $approve, $remarks): void {
            $request->update([
                'status' => $approve ? ApprovalStatus::Approved : ApprovalStatus::Rejected,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_remarks' => $remarks,
            ]);

            if ($approve) {
                $this->reopen->handle($actor, $request->enquiry, $request->reason, $request->id);
            }
        });

        $request->requester?->notify(new ReopenDecided($request));

        return $request;
    }
}
