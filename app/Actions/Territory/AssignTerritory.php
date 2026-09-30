<?php

namespace App\Actions\Territory;

use App\Enums\TerritoryLevel;
use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\TerritoryAssignment;
use App\Services\TerritoryService;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Assigns a salesman to a district, tehsil or village (SRS §6). Only one active
 * primary salesman per area: an existing primary must be replaced explicitly,
 * which ends the old assignment and keeps it in history.
 */
class AssignTerritory
{
    public function __construct(private readonly TerritoryService $territory) {}

    public function handle(Employee $employee, TerritoryLevel $level, int $areaId, bool $isPrimary, CarbonInterface $effectiveFrom, bool $replaceExisting = false): TerritoryAssignment
    {
        if (! $employee->is_active) {
            throw new BusinessRuleException(__('Choose an active employee.'), 'inactive_employee');
        }

        $duplicate = TerritoryAssignment::query()->active()->where('employee_id', $employee->id)
            ->where('level', $level)->where($level->column(), $areaId)->exists();

        if ($duplicate) {
            throw new BusinessRuleException(__('This employee already covers this area.'), 'territory_duplicate');
        }

        return DB::transaction(function () use ($employee, $level, $areaId, $isPrimary, $effectiveFrom, $replaceExisting): TerritoryAssignment {
            if ($isPrimary && ($current = $this->territory->currentPrimary($level, $areaId))) {
                if (! $replaceExisting) {
                    throw new BusinessRuleException(
                        __(':name is already the primary salesman for this area. Replace them explicitly to continue.', ['name' => $current->employee->name]),
                        'territory_primary_exists',
                    );
                }

                $current->update(['is_active' => false, 'effective_to' => $effectiveFrom->copy()->subDay(), 'end_reason' => __('Replaced by :name', ['name' => $employee->name])]);
            }

            try {
                return TerritoryAssignment::create([
                    'employee_id' => $employee->id,
                    'level' => $level,
                    $level->column() => $areaId,
                    'is_primary' => $isPrimary,
                    'is_active' => true,
                    'effective_from' => $effectiveFrom,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new BusinessRuleException(__('Another primary salesman was assigned to this area at the same time. Refresh and try again.'), 'territory_primary_exists');
            }
        });
    }

    public function end(TerritoryAssignment $assignment, string $reason): TerritoryAssignment
    {
        if (! $assignment->is_active) {
            throw new BusinessRuleException(__('This assignment has already ended.'), 'territory_ended');
        }

        $assignment->update(['is_active' => false, 'effective_to' => today(), 'end_reason' => $reason]);

        return $assignment;
    }
}
