<?php

namespace App\Actions\Telecaller;

use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\Enquiry;

/**
 * Take/Claim from the common validation queue (SRS §10). A single conditional
 * UPDATE makes the claim atomic, so two telecallers can never hold the same enquiry.
 * A claim lapses after config('erp.crm.claim_timeout_minutes').
 */
class ClaimEnquiry
{
    public function handle(Employee $telecaller, Enquiry $enquiry): Enquiry
    {
        $expiredBefore = now()->subMinutes(config('erp.crm.claim_timeout_minutes'));

        $claimed = Enquiry::query()
            ->whereKey($enquiry->id)
            ->whereNull('pipeline_stage_id')
            ->whereNull('closed_at')
            ->where(fn ($query) => $query
                ->whereNull('claimed_by_employee_id')
                ->orWhere('claimed_by_employee_id', $telecaller->id)
                ->orWhere('claimed_at', '<', $expiredBefore))
            ->update(['claimed_by_employee_id' => $telecaller->id, 'claimed_at' => now()]);

        $enquiry->refresh();

        if ($claimed === 1) {
            return $enquiry;
        }

        if ($enquiry->pipeline_stage_id !== null || $enquiry->closed_at !== null) {
            throw new BusinessRuleException(__('This enquiry is no longer awaiting validation.'), 'not_in_validation_queue');
        }

        throw new BusinessRuleException(
            __('Already claimed by :name.', ['name' => $enquiry->claimedBy?->name ?? __('another telecaller')]),
            'already_claimed',
        );
    }

    public function release(Employee $telecaller, Enquiry $enquiry): void
    {
        Enquiry::query()->whereKey($enquiry->id)->where('claimed_by_employee_id', $telecaller->id)
            ->update(['claimed_by_employee_id' => null, 'claimed_at' => null]);
    }
}
