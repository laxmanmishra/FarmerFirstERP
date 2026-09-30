<?php

namespace App\Events;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An order with its fulfilment and department tasks was created from an approved deal.
 */
class OrderBooked implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Order $order, public readonly User $actor) {}
}
