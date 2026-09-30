<?php

namespace App\Console\Commands;

use App\Models\Enquiry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Claims lapse after the configured timeout anyway (ClaimEnquiry accepts expired
 * claims); this clears them so queues and counters show the enquiry as free.
 */
#[Signature('crm:release-stale-claims')]
#[Description('Return telecaller claims older than the timeout to the common queue')]
class ReleaseStaleClaims extends Command
{
    public function handle(): int
    {
        $released = Enquiry::query()
            ->whereNotNull('claimed_by_employee_id')
            ->where('claimed_at', '<', now()->subMinutes(config('erp.crm.claim_timeout_minutes')))
            ->update(['claimed_by_employee_id' => null, 'claimed_at' => null]);

        $this->info("Released {$released} claims.");

        return self::SUCCESS;
    }
}
