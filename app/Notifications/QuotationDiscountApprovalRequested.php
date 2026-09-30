<?php

namespace App\Notifications;

use App\Models\Quotation;
use App\Support\Money;

class QuotationDiscountApprovalRequested extends ErpNotification
{
    public function __construct(public readonly Quotation $quotation)
    {
        $this->priority = 'high';
    }

    public function message(): string
    {
        return __('Quotation :no needs discount approval: :amount (:percent%).', [
            'no' => $this->quotation->reference(),
            'amount' => Money::format($this->quotation->discount_total),
            'percent' => $this->quotation->discount_percent,
        ]);
    }

    public function url(): ?string
    {
        return route('sales.quotations.show', $this->quotation);
    }
}
