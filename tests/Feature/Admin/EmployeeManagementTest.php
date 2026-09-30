<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Employees\Index;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_employee_belongs_to_several_departments_and_gets_a_series_code(): void
    {
        $sales = Department::query()->where('code', 'SALES')->value('id');
        $delivery = Department::query()->where('code', 'DELIVERY')->value('id');
        $login = $this->userWithRole('Salesman');

        Livewire::actingAs($this->userWithRole('Owner'))->test(Index::class)
            ->call('create')
            ->set('name', 'Ramesh Verma')
            ->set('mobile', '9123456789')
            ->set('user_id', $login->id)
            ->set('department_ids', [$sales, $delivery])
            ->set('primary_department_id', $delivery)
            ->set('branch_ids', [$this->headOffice()->id])
            ->call('save')
            ->assertHasNoErrors();

        $employee = Employee::query()->where('name', 'Ramesh Verma')->firstOrFail();
        $this->assertSame('EMP0001', $employee->employee_code);
        $this->assertSame($login->id, $employee->user_id);
        $this->assertEqualsCanonicalizing([$sales, $delivery], $employee->departments->pluck('id')->all());
        $this->assertSame($delivery, $employee->departments->firstWhere('pivot.is_primary', true)->id);
        $this->assertTrue($login->fresh()->canAccessBranch($this->headOffice()));
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $employee->id, 'event' => 'assignments_changed']);
    }

    public function test_department_and_branch_are_required(): void
    {
        Livewire::actingAs($this->userWithRole('Owner'))->test(Index::class)
            ->call('create')
            ->set('name', 'No Department')
            ->call('save')
            ->assertHasErrors(['department_ids', 'branch_ids']);
    }

    public function test_reporting_line_cannot_form_a_cycle(): void
    {
        $manager = Employee::factory()->create();
        $salesman = Employee::factory()->create(['reports_to_id' => $manager->id]);
        $sales = Department::query()->where('code', 'SALES')->value('id');

        Livewire::actingAs($this->userWithRole('Owner'))->test(Index::class)
            ->call('edit', $manager->id)
            ->set('reports_to_id', $salesman->id)
            ->set('department_ids', [$sales])
            ->set('branch_ids', [$this->headOffice()->id])
            ->call('save')
            ->assertDispatched('toast', type: 'error');

        $this->assertNull($manager->fresh()->reports_to_id);
    }

    public function test_team_member_ids_include_indirect_reports(): void
    {
        $owner = Employee::factory()->create();
        $manager = Employee::factory()->create(['reports_to_id' => $owner->id]);
        $salesman = Employee::factory()->create(['reports_to_id' => $manager->id]);
        Employee::factory()->create();

        $this->assertEqualsCanonicalizing([$owner->id, $manager->id, $salesman->id], $owner->teamMemberIds());
        $this->assertEqualsCanonicalizing([$manager->id, $salesman->id], $manager->teamMemberIds());
    }

    public function test_user_branch_access_follows_employee_branches(): void
    {
        $second = Branch::create(['company_id' => $this->headOffice()->company_id, 'code' => 'BR2', 'name' => 'Second Branch']);
        $user = $this->userWithRole('Salesman');
        $this->employeeFor($user);

        $this->assertTrue($user->canAccessBranch($this->headOffice()));
        $this->assertFalse($user->canAccessBranch($second));
        $this->assertTrue($this->userWithRole('Owner')->canAccessBranch($second));
    }
}
