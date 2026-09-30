<?php

namespace App\Listeners;

use App\Actions\Deals\ConvertWonEnquiry;
use App\Events\EnquiryWon;

/**
 * Runs synchronously after the WON transaction commits, so the salesman lands on a
 * ready deal. Idempotent, so a replayed event cannot create a second customer or deal.
 */
class CreateCustomerAndDeal
{
    public function __construct(private readonly ConvertWonEnquiry $convert) {}

    public function handle(EnquiryWon $event): void
    {
        $this->convert->handle($event->enquiry, $event->actor);
    }
}
