<?php

namespace App\Services;

use App\Enums\TerritoryLevel;
use App\Models\Employee;
use App\Models\TerritoryAssignment;
use App\Models\Village;

/**
 * Resolves territory ownership (SRS §6). The most specific active primary
 * assignment wins: village, then tehsil, then district.
 */
class TerritoryService
{
    public function primarySalesmanFor(Village $village): ?Employee
    {
        $village->loadMissing('tehsil');

        $candidates = [
            [TerritoryLevel::Village, $village->id],
            [TerritoryLevel::Tehsil, $village->tehsil_id],
            [TerritoryLevel::District, $village->tehsil->district_id],
        ];

        foreach ($candidates as [$level, $areaId]) {
            $assignment = TerritoryAssignment::query()
                ->with('employee')
                ->where('primary_scope', $level->value.':'.$areaId)
                ->first();

            if ($assignment?->employee?->is_active) {
                return $assignment->employee;
            }
        }

        return null;
    }

    public function currentPrimary(TerritoryLevel $level, int $areaId): ?TerritoryAssignment
    {
        return TerritoryAssignment::query()->with('employee')->where('primary_scope', $level->value.':'.$areaId)->first();
    }
}
