<?php

namespace App\Events;

use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An enquiry reached a pipeline stage flagged final + completion (WON).
 * Phase 3 listens to run the customer duplicate check and create the customer (SRS §12).
 */
class EnquiryWon implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Enquiry $enquiry, public readonly User $actor) {}
}
