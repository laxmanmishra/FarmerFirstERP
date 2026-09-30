<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Financial year value object. Default start month is April (Indian FY, SRS v6.1 §9).
 */
final readonly class FinancialYear
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    public static function forDate(CarbonInterface $date, int $startMonth = 4): self
    {
        if ($startMonth < 1 || $startMonth > 12) {
            throw new InvalidArgumentException("Invalid financial year start month [{$startMonth}].");
        }

        $date = CarbonImmutable::instance($date);
        $startYear = $date->month >= $startMonth ? $date->year : $date->year - 1;
        $start = CarbonImmutable::create($startYear, $startMonth, 1)->startOfDay();

        return new self($start, $start->addYear()->subDay()->endOfDay());
    }

    /**
     * "2026-27" for April 2026 – March 2027; "2026" when the FY equals the calendar year.
     */
    public function label(): string
    {
        if ($this->start->year === $this->end->year) {
            return (string) $this->start->year;
        }

        return $this->start->year.'-'.substr((string) $this->end->year, -2);
    }

    public function contains(CarbonInterface $date): bool
    {
        return $date->betweenIncluded($this->start, $this->end);
    }
}
