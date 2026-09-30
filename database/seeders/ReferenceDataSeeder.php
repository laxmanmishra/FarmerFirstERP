<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Reference/configuration data required in every environment, including tests.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            GeographySeeder::class,
            OrganisationSeeder::class,
            NumberSeriesSeeder::class,
        ]);
    }
}
