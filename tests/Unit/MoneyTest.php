<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_arithmetic_is_exact_decimal(): void
    {
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
        $this->assertSame('785000.00', Money::sub('790000', '5000'));
        $this->assertSame('1570000.00', Money::mul('785000', 2));
    }

    public function test_percentages_round_half_up_to_paise(): void
    {
        $this->assertSame('93600.00', Money::percentOf('780000', '12'));
        $this->assertSame('0.02', Money::percentOf('0.15', '10'));
        $this->assertSame('0.64', Money::ratioPercent('5000', '785000'));
        $this->assertSame('0.00', Money::ratioPercent('5', '0'));
    }

    public function test_indian_digit_grouping(): void
    {
        $this->assertSame('₹9,24,650.00', Money::format('924650'));
        $this->assertSame('₹1,23,45,678.50', Money::format('12345678.5'));
        $this->assertSame('₹999.00', Money::format('999'));
        $this->assertSame('-₹5,000.00', Money::format('-5000'));
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::normalise('12abc');
    }
}
