<?php

namespace Tests\Unit;

use App\Support\FinancialYear;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FinancialYearTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: int, 2: string, 3: string, 4: string}>
     */
    public static function dates(): array
    {
        return [
            'first day of Indian FY' => ['2026-04-01', 4, '2026-27', '2026-04-01', '2027-03-31'],
            'last day of Indian FY' => ['2027-03-31', 4, '2026-27', '2026-04-01', '2027-03-31'],
            'January belongs to previous FY' => ['2027-01-15', 4, '2026-27', '2026-04-01', '2027-03-31'],
            'calendar-year FY' => ['2026-09-30', 1, '2026', '2026-01-01', '2026-12-31'],
            'July FY' => ['2026-06-30', 7, '2025-26', '2025-07-01', '2026-06-30'],
        ];
    }

    #[DataProvider('dates')]
    public function test_financial_year_boundaries(string $date, int $startMonth, string $label, string $start, string $end): void
    {
        $year = FinancialYear::forDate(CarbonImmutable::parse($date), $startMonth);

        $this->assertSame($label, $year->label());
        $this->assertSame($start, $year->start->toDateString());
        $this->assertSame($end, $year->end->toDateString());
        $this->assertTrue($year->contains(CarbonImmutable::parse($date)));
    }

    public function test_invalid_start_month_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FinancialYear::forDate(CarbonImmutable::now(), 13);
    }
}
