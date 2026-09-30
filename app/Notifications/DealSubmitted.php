<?php

namespace App\Notifications;

use App\Models\Deal;
use App\Support\Money;

class DealSubmitted extends ErpNotification
{
    public function __construct(public readonly Deal $deal)
    {
        $this->priority = 'high';
    }

    public function message(): string
    {
        return __('Deal :no (:customer, :value) is ready for approval.', [
            'no' => $this->deal->deal_no,
            'customer' => $this->deal->customer->name,
            'value' => Money::format($this->deal->deal_value),
        ]);
    }

    public function url(): ?string
    {
        return route('sales.deals.show', $this->deal);
    }
}
