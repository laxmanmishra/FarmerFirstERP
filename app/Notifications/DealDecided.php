<?php

namespace App\Notifications;

use App\Enums\DealDecision;
use App\Models\Deal;

class DealDecided extends ErpNotification
{
    public function __construct(public readonly Deal $deal, public readonly DealDecision $decision, public readonly ?string $remarks)
    {
        $this->priority = $decision === DealDecision::Approved ? 'normal' : 'high';
    }

    public function message(): string
    {
        return trim(__('Deal :no: :decision.', ['no' => $this->deal->deal_no, 'decision' => mb_strtolower($this->decision->label())]).' '.($this->remarks ?? ''));
    }

    public function url(): ?string
    {
        return route('sales.deals.show', $this->deal);
    }
}
