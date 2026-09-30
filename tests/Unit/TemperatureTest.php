<?php

namespace Tests\Unit;

use App\Enums\Temperature;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SRS §8: 0–1 days Extra Hot, 2–6 Hot, 7–14 Warm, 15+ Cold.
 */
class TemperatureTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: Temperature}>
     */
    public static function bands(): array
    {
        return [
            'today' => [0, Temperature::ExtraHot],
            'tomorrow' => [1, Temperature::ExtraHot],
            'two days' => [2, Temperature::Hot],
            'six days' => [6, Temperature::Hot],
            'seven days' => [7, Temperature::Warm],
            'fourteen days' => [14, Temperature::Warm],
            'fifteen days' => [15, Temperature::Cold],
            'passed date' => [-3, Temperature::ExtraHot],
        ];
    }

    #[DataProvider('bands')]
    public function test_temperature_band(int $days, Temperature $expected): void
    {
        $today = CarbonImmutable::parse('2026-10-01 18:30');

        $this->assertSame($expected, Temperature::fromExpectedDate($today->addDays($days)->startOfDay(), $today));
    }
}
