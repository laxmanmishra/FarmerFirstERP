<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Development-only login accounts, one per role (master prompt §55).
 * Password for all: "password". Refuses to run in production.
 */
class DemoUsersSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo users must never be seeded in production.');
        }

        $branch = Branch::query()->where('code', 'HO')->firstOrFail();

        // [email local-part, name, role, department code, designation code, reports to (email local-part)]
        $people = [
            ['superadmin', 'System Administrator', User::SUPER_ADMIN_ROLE, 'ADMIN', 'SYSTEM_ADMINISTRATOR', null],
            ['owner', 'Owner', User::OWNER_ROLE, 'MANAGEMENT', 'OWNER', null],
            ['sales.manager', 'Suresh Patel', 'Sales Manager', 'SALES', 'SALES_MANAGER', 'owner'],
            ['dwarika', 'Dwarika Prasad', 'Salesman', 'SALES', 'SALES_EXECUTIVE', 'sales.manager'],
            ['salesman2', 'Ramesh Verma', 'Salesman', 'SALES', 'SALES_EXECUTIVE', 'sales.manager'],
            ['telecaller', 'Pooja Sharma', 'Telecaller', 'TELECALLING', 'TELECALLER', 'sales.manager'],
            ['retail', 'Anil Joshi', 'Retail Employee', 'RETAIL_FINANCE', 'EXECUTIVE', 'owner'],
            ['accounts', 'Kavita Rao', 'Accounts Employee', 'ACCOUNTS', 'EXECUTIVE', 'owner'],
            ['inventory', 'Mahesh Yadav', 'Inventory Employee', 'INVENTORY', 'EXECUTIVE', 'owner'],
            ['rto', 'Vikas Tiwari', 'RTO Employee', 'RTO', 'EXECUTIVE', 'owner'],
            ['insurance', 'Neha Gupta', 'Insurance Employee', 'INSURANCE', 'EXECUTIVE', 'owner'],
            ['pdi', 'Rajesh Kumar', 'PDI Employee', 'PDI', 'TECHNICIAN', 'owner'],
            ['delivery', 'Sunil Mehra', 'Delivery Employee', 'DELIVERY', 'EXECUTIVE', 'owner'],
        ];

        $employeesByKey = [];

        foreach ($people as $index => [$key, $name, $role, $departmentCode, $designationCode, $reportsTo]) {
            $user = User::query()->firstOrCreate(['email' => "{$key}@farmerfirst.test"], [
                'name' => $name,
                'password' => self::DEMO_PASSWORD,
                'current_branch_id' => $branch->id,
            ]);
            $user->syncRoles([$role]);

            $employee = Employee::query()->firstOrCreate(['user_id' => $user->id], [
                'employee_code' => sprintf('EMP%04d', $index + 1),
                'name' => $name,
                'email' => $user->email,
                'designation_id' => Designation::query()->where('code', $designationCode)->value('id'),
                'reports_to_id' => $reportsTo ? $employeesByKey[$reportsTo]->id : null,
                'date_of_joining' => now()->subYear()->startOfMonth(),
            ]);
            $employee->branches()->syncWithoutDetaching([$branch->id => ['is_primary' => true]]);
            $employee->departments()->syncWithoutDetaching([
                Department::query()->where('code', $departmentCode)->value('id') => ['is_primary' => true],
            ]);

            $employeesByKey[$key] = $employee;
        }
    }
}
