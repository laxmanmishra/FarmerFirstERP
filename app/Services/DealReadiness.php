<?php

namespace App\Services;

use App\Enums\QuotationStatus;
use App\Models\Deal;
use App\Support\Money;

/**
 * "Deal Ready means configured information/documents are complete" (SRS §14).
 * Returns the list of what is missing; empty means ready. Document requirements
 * are added by the Document module (Phase 4).
 */
class DealReadiness
{
    /**
     * @return list<string>
     */
    public function missing(Deal $deal): array
    {
        $deal->loadMissing(['quotation', 'items', 'customer']);
        $missing = [];

        if ($deal->quotation === null || $deal->quotation->status !== QuotationStatus::Accepted) {
            $missing[] = __('Link an accepted quotation (the approved commercial figures come from it).');
        }

        if ($deal->items->isEmpty() || Money::compare($deal->deal_value, 0) <= 0) {
            $missing[] = __('The deal has no value.');
        }

        if ($deal->expected_delivery_date === null) {
            $missing[] = __('Set the expected delivery date.');
        } elseif ($deal->expected_delivery_date->isBefore(today())) {
            $missing[] = __('The expected delivery date is in the past.');
        }

        if ($deal->finance_required && Money::compare($deal->finance_amount, 0) <= 0) {
            $missing[] = __('Finance is required but no finance amount is set.');
        }

        if (Money::compare($deal->finance_amount, $deal->deal_value) > 0) {
            $missing[] = __('The finance amount exceeds the deal value.');
        }

        if (Money::compare($deal->booking_amount, $deal->customer_contribution) > 0) {
            $missing[] = __('The booking amount exceeds the customer contribution.');
        }

        if (blank($deal->customer?->mobile) || $deal->customer?->village_id === null) {
            $missing[] = __('The customer needs a mobile number and village.');
        }

        return $missing;
    }
}
