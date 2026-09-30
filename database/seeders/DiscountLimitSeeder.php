<?php

namespace Database\Seeders;

use App\Models\DiscountLimit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Initial discount authority per role (SRS v6.1 §4). Editable in System Settings → Discounts;
 * existing rows are never overwritten. Confirm the values during business sign-off.
 */
class DiscountLimitSeeder extends Seeder
{
    public function run(): void
    {
        $limits = [
            User::OWNER_ROLE => ['100.00', null],
            'Sales Manager' => ['5.00', '50000.00'],
            'Salesman' => ['1.00', '10000.00'],
        ];

        foreach ($limits as $roleName => [$percent, $amount]) {
            if ($role = Role::query()->where('name', $roleName)->first()) {
                DiscountLimit::query()->firstOrCreate(['role_id' => $role->id], ['max_percent' => $percent, 'max_amount' => $amount]);
            }
        }
    }
}
