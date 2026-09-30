<?php

namespace App\Events;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A deal was approved. Phase 4 listens to create the ORDER/BOOKING and its
 * fulfilment tasks (SRS §15).
 */
class DealApproved implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Deal $deal, public readonly User $actor) {}
}
