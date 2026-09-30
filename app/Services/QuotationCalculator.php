<?php

namespace App\Services;

use App\Enums\LineType;
use App\Exceptions\BusinessRuleException;
use App\Support\Money;

/**
 * Commercial arithmetic shared by quotations and deals (SRS v6.1 §4).
 *
 * Per line: gross = qty × unit price; taxable = gross − discount; tax = taxable × tax%;
 * line total = taxable + tax. Charges (RTO, insurance, handling) are listed separately.
 * Net = items + charges − exchange value; customer contribution = net − finance amount.
 */
class QuotationCalculator
{
    /**
     * @param  list<array{line_type: string, product_id?: ?int, product_variant_id?: ?int, description: string, quantity: int|string, unit_price: string|int|float, discount_amount?: string|int|float|null, tax_percent?: string|int|float|null}>  $lines
     * @return array{lines: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function calculate(array $lines, string|int|float|null $exchangeValue = 0, string|int|float|null $financeAmount = 0): array
    {
        $computed = [];
        $totals = ['gross_total' => '0.00', 'discount_total' => '0.00', 'tax_total' => '0.00', 'items_total' => '0.00', 'charges_total' => '0.00'];

        foreach (array_values($lines) as $index => $line) {
            $quantity = max(1, (int) $line['quantity']);
            $gross = Money::mul($line['unit_price'], $quantity);
            $discount = Money::normalise($line['discount_amount'] ?? 0);

            if (Money::isNegative($line['unit_price']) || Money::isNegative($discount)) {
                throw new BusinessRuleException(__('Prices and discounts cannot be negative.'), 'negative_amount');
            }

            if (Money::compare($discount, $gross) > 0) {
                throw new BusinessRuleException(__('The discount on ":line" is larger than its value.', ['line' => $line['description']]), 'discount_exceeds_value');
            }

            $taxable = Money::sub($gross, $discount);
            $tax = Money::percentOf($taxable, $line['tax_percent'] ?? 0);
            $lineTotal = Money::add($taxable, $tax);
            $type = LineType::from($line['line_type']);

            $computed[] = [
                'line_type' => $type,
                'product_id' => $line['product_id'] ?? null,
                'product_variant_id' => $line['product_variant_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $quantity,
                'unit_price' => Money::normalise($line['unit_price']),
                'discount_amount' => $discount,
                'tax_percent' => Money::normalise($line['tax_percent'] ?? 0),
                'tax_amount' => $tax,
                'line_total' => $lineTotal,
                'sort_order' => ($index + 1) * 10,
            ];

            $totals['gross_total'] = Money::add($totals['gross_total'], $gross);
            $totals['discount_total'] = Money::add($totals['discount_total'], $discount);
            $totals['tax_total'] = Money::add($totals['tax_total'], $tax);
            $bucket = $type === LineType::Charge ? 'charges_total' : 'items_total';
            $totals[$bucket] = Money::add($totals[$bucket], $lineTotal);
        }

        $exchange = Money::normalise($exchangeValue);
        $finance = Money::normalise($financeAmount);
        $net = Money::sub(Money::add($totals['items_total'], $totals['charges_total']), $exchange);

        if (Money::isNegative($exchange) || Money::isNegative($finance)) {
            throw new BusinessRuleException(__('Exchange value and finance amount cannot be negative.'), 'negative_amount');
        }

        if (Money::isNegative($net)) {
            throw new BusinessRuleException(__('The exchange value is larger than the quotation value.'), 'exchange_exceeds_value');
        }

        if (Money::compare($finance, $net) > 0) {
            throw new BusinessRuleException(__('The finance amount cannot exceed the net amount.'), 'finance_exceeds_value');
        }

        $totals += [
            'exchange_value' => $exchange,
            'net_amount' => $net,
            'finance_amount' => $finance,
            'customer_contribution' => Money::sub($net, $finance),
            'discount_percent' => Money::ratioPercent($totals['discount_total'], $totals['gross_total']),
        ];

        return ['lines' => $computed, 'totals' => $totals];
    }
}
