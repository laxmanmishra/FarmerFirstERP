<?php

namespace App\Actions\Deals;

use App\Enums\QuotationStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Deal;
use App\Models\Quotation;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Copies the accepted quotation's commercial snapshot onto the deal, so approved
 * figures flow Quotation → Deal → Order without re-entry (SRS v6.1 §4).
 */
class ApplyQuotationToDeal
{
    public function handle(Deal $deal, Quotation $quotation): Deal
    {
        if ($quotation->status !== QuotationStatus::Accepted || $quotation->enquiry_id !== $deal->enquiry_id) {
            throw new BusinessRuleException(__('Only an accepted quotation of this enquiry can be applied to the deal.'), 'quotation_not_applicable');
        }

        if (! $deal->isEditable()) {
            throw new BusinessRuleException(__('The deal is under approval or closed; its figures cannot change.'), 'deal_locked');
        }

        return DB::transaction(function () use ($deal, $quotation): Deal {
            $deal->items()->delete();
            $deal->items()->createMany($quotation->items->map->commercialAttributes()->all());

            $finance = $quotation->finance_amount;

            $deal->update([
                'quotation_id' => $quotation->id,
                'gross_total' => $quotation->gross_total,
                'discount_total' => $quotation->discount_total,
                'tax_total' => $quotation->tax_total,
                'charges_total' => $quotation->charges_total,
                'exchange_value' => $quotation->exchange_value,
                'deal_value' => $quotation->net_amount,
                'finance_required' => Money::compare($finance, 0) > 0,
                'finance_amount' => $finance,
                'customer_contribution' => Money::sub($quotation->net_amount, $finance),
            ]);

            return $deal;
        });
    }
}
