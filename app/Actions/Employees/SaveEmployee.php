<?php

namespace App\Actions\Employees;

use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Services\AuditService;
use App\Services\NumberSeriesService;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates an employee with multi-branch and multi-department
 * membership (SRS v6.0 J) and a cycle-free reporting line.
 */
class SaveEmployee
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly NumberSeriesService $numbers,
    ) {}

    /**
     * @param  array{name: string, mobile: ?string, email: ?string, designation_id: ?int, reports_to_id: ?int, user_id: ?int, date_of_joining: ?string}  $attributes
     * @param  list<int>  $departmentIds
     * @param  list<int>  $branchIds
     */
    public function handle(
        array $attributes,
        array $departmentIds,
        ?int $primaryDepartmentId,
        array $branchIds,
        ?int $primaryBranchId,
        ?Employee $employee = null,
    ): Employee {
        if ($employee !== null && $attributes['reports_to_id'] !== null && $this->wouldCreateCycle($employee, $attributes['reports_to_id'])) {
            throw new BusinessRuleException(__('An employee cannot report to themselves or to someone in their own team.'), 'reporting_cycle');
        }

        return DB::transaction(function () use ($attributes, $departmentIds, $primaryDepartmentId, $branchIds, $primaryBranchId, $employee): Employee {
            if ($employee === null) {
                $employee = Employee::create([...$attributes, 'employee_code' => $this->numbers->next('employee')]);
                $before = ['departments' => [], 'branches' => []];
            } else {
                $before = $this->assignments($employee);
                $employee->update($attributes);
            }

            $employee->departments()->sync($this->pivot($departmentIds, $primaryDepartmentId));
            $employee->branches()->sync($this->pivot($branchIds, $primaryBranchId));

            $after = $this->assignments($employee);

            if ($before !== $after) {
                $this->audit->record('assignments_changed', 'employees', $employee, $before, $after);
            }

            return $employee;
        });
    }

    private function wouldCreateCycle(Employee $employee, int $managerId): bool
    {
        return in_array($managerId, $employee->teamMemberIds(), true);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{is_primary: bool}>
     */
    private function pivot(array $ids, ?int $primaryId): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $primaryId = in_array($primaryId, $ids, true) ? $primaryId : ($ids[0] ?? null);

        return collect($ids)->mapWithKeys(fn (int $id): array => [$id => ['is_primary' => $id === $primaryId]])->all();
    }

    /**
     * @return array{departments: list<int>, branches: list<int>}
     */
    private function assignments(Employee $employee): array
    {
        return [
            'departments' => $employee->departments()->pluck('departments.id')->sort()->values()->all(),
            'branches' => $employee->branches()->pluck('branches.id')->sort()->values()->all(),
        ];
    }
}
