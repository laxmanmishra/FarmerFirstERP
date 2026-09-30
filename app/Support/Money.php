<?php

namespace App\Support;

/**
 * Decimal-string money arithmetic (bcmath, 2 dp). Money is never handled as float.
 */
final class Money
{
    public static function add(string|int|float|null ...$values): string
    {
        return array_reduce($values, fn (string $carry, $value) => bcadd($carry, self::normalise($value), 2), '0.00');
    }

    public static function sub(string|int|float|null $a, string|int|float|null $b): string
    {
        return bcsub(self::normalise($a), self::normalise($b), 2);
    }

    public static function mul(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::round(bcmul(self::normalise($a), self::normalise($b), 6));
    }

    /**
     * $amount × $percent / 100, rounded half-up to paise.
     */
    public static function percentOf(string|int|float|null $amount, string|int|float|null $percent): string
    {
        return self::round(bcdiv(bcmul(self::normalise($amount), self::normalise($percent), 6), '100', 6));
    }

    /**
     * $part as a percentage of $whole (2 dp); 0 when $whole is zero.
     */
    public static function ratioPercent(string|int|float|null $part, string|int|float|null $whole): string
    {
        return bccomp(self::normalise($whole), '0', 2) === 0
            ? '0.00'
            : self::round(bcdiv(bcmul(self::normalise($part), '100', 6), self::normalise($whole), 6));
    }

    public static function compare(string|int|float|null $a, string|int|float|null $b): int
    {
        return bccomp(self::normalise($a), self::normalise($b), 2);
    }

    public static function isNegative(string|int|float|null $value): bool
    {
        return self::compare($value, 0) < 0;
    }

    public static function format(string|int|float|null $value): string
    {
        $normalised = self::normalise($value);
        $negative = str_starts_with($normalised, '-');
        [$rupees, $paise] = explode('.', ltrim($normalised, '-'));

        // Indian digit grouping: 12,34,567.00
        $last3 = substr($rupees, -3);
        $rest = substr($rupees, 0, -3);
        $grouped = $rest === '' ? $last3 : preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3;

        return ($negative ? '-' : '').'₹'.$grouped.'.'.$paise;
    }

    public static function normalise(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        $string = is_float($value) ? number_format($value, 6, '.', '') : trim((string) $value);

        if (! is_numeric($string)) {
            throw new \InvalidArgumentException("Invalid money value [{$string}].");
        }

        return bcadd($string, '0', 2);
    }

    private static function round(string $value): string
    {
        $offset = str_starts_with($value, '-') ? '-0.005' : '0.005';

        return bcadd(bcadd($value, $offset, 6), '0', 2);
    }
}
