<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data is seeded in every environment; demo data only outside production.
     */
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        if (! app()->isProduction()) {
            $this->call([DemoUsersSeeder::class, DemoCatalogueSeeder::class, DemoCrmSeeder::class, DemoSalesSeeder::class]);
        }
    }
}
