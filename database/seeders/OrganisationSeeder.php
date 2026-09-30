<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Database\Seeder;

class OrganisationSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->firstOrCreate(['code' => 'FF'], [
            'name' => 'Farmer First',
            'legal_name' => 'Farmer First',
            'financial_year_start_month' => 4,
        ]);

        Branch::query()->firstOrCreate(['code' => 'HO'], [
            'company_id' => $company->id,
            'name' => 'Head Office',
        ]);

        $departments = [
            ['MANAGEMENT', 'Management', false],
            ['ADMIN', 'Administration', false],
            ['SALES', 'Sales', false],
            ['TELECALLING', 'Telecalling', false],
            ['RETAIL_FINANCE', 'Retail & Finance', true],
            ['ACCOUNTS', 'Accounts', true],
            ['INVENTORY', 'Inventory', true],
            ['RTO', 'RTO / Back Office', true],
            ['INSURANCE', 'Insurance', true],
            ['PDI', 'PDI / Workshop', true],
            ['DELIVERY', 'Delivery', true],
        ];

        foreach ($departments as $order => [$code, $name, $operational]) {
            Department::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'is_operational' => $operational,
                'sort_order' => ($order + 1) * 10,
            ]);
        }

        $designations = [
            'OWNER' => 'Owner',
            'GENERAL_MANAGER' => 'General Manager',
            'SALES_MANAGER' => 'Sales Manager',
            'SALES_EXECUTIVE' => 'Sales Executive',
            'TELECALLER' => 'Telecaller',
            'DEPARTMENT_MANAGER' => 'Department Manager',
            'EXECUTIVE' => 'Executive',
            'TECHNICIAN' => 'Technician',
            'SYSTEM_ADMINISTRATOR' => 'System Administrator',
        ];

        foreach ($designations as $code => $name) {
            Designation::query()->firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
