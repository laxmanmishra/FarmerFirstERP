<?php

namespace Tests\Feature\Sales;

use App\Exceptions\BusinessRuleException;
use App\Services\QuotationCalculator;
use Tests\TestCase;

class QuotationCalculatorTest extends TestCase
{
    public function test_totals_with_discount_tax_charges_exchange_and_finance(): void
    {
        $result = app(QuotationCalculator::class)->calculate([
            ['line_type' => 'product', 'description' => 'Tractor', 'quantity' => 1, 'unit_price' => '785000', 'discount_amount' => '5000', 'tax_percent' => '12'],
            ['line_type' => 'accessory', 'description' => 'Hitch', 'quantity' => 2, 'unit_price' => '5000', 'tax_percent' => '18'],
            ['line_type' => 'charge', 'description' => 'RTO', 'quantity' => 1, 'unit_price' => '14500'],
        ], exchangeValue: '200000', financeAmount: '500000');

        $totals = $result['totals'];
        $this->assertSame('809500.00', $totals['gross_total']);       // includes charge lines
        $this->assertSame('5000.00', $totals['discount_total']);
        $this->assertSame('95400.00', $totals['tax_total']);          // 780000×12% + 10000×18%
        $this->assertSame('885400.00', $totals['items_total']);       // 873600 + 11800
        $this->assertSame('14500.00', $totals['charges_total']);
        $this->assertSame('699900.00', $totals['net_amount']);        // 885400 + 14500 − 200000
        $this->assertSame('199900.00', $totals['customer_contribution']);
        $this->assertSame('0.62', $totals['discount_percent']);
        $this->assertSame('873600.00', $result['lines'][0]['line_total']);
    }

    public function test_discount_larger_than_line_value_is_refused(): void
    {
        $this->expectException(BusinessRuleException::class);

        app(QuotationCalculator::class)->calculate([['line_type' => 'product', 'description' => 'X', 'quantity' => 1, 'unit_price' => '100', 'discount_amount' => '101']]);
    }

    public function test_finance_cannot_exceed_net_amount(): void
    {
        $this->expectException(BusinessRuleException::class);

        app(QuotationCalculator::class)->calculate([['line_type' => 'product', 'description' => 'X', 'quantity' => 1, 'unit_price' => '100000']], financeAmount: '100001');
    }

    public function test_exchange_cannot_exceed_value(): void
    {
        $this->expectException(BusinessRuleException::class);

        app(QuotationCalculator::class)->calculate([['line_type' => 'product', 'description' => 'X', 'quantity' => 1, 'unit_price' => '100000']], exchangeValue: '100000.01');
    }
}
