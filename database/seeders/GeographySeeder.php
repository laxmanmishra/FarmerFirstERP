<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\State;
use App\Models\Tehsil;
use App\Models\Village;
use Illuminate\Database\Seeder;

/**
 * Minimal sample territory for development. Production territory is loaded
 * through the Excel/CSV geography import (SRS §6).
 */
class GeographySeeder extends Seeder
{
    public function run(): void
    {
        $state = State::query()->firstOrCreate(['code' => 'MP'], ['name' => 'Madhya Pradesh']);

        $sample = [
            'Sehore' => [
                'Sehore' => ['Bilkisganj', 'Shyampur', 'Jhagariya'],
                'Ashta' => ['Kannod Mirzapur', 'Arniya', 'Mainwada'],
            ],
            'Raisen' => [
                'Raisen' => ['Sanchi', 'Khamkheda', 'Deopura'],
                'Gairatganj' => ['Silwani Road', 'Pipaliya', 'Bamhori'],
            ],
        ];

        foreach ($sample as $districtName => $tehsils) {
            $district = District::query()->firstOrCreate(['state_id' => $state->id, 'name' => $districtName]);

            foreach ($tehsils as $tehsilName => $villages) {
                $tehsil = Tehsil::query()->firstOrCreate(['district_id' => $district->id, 'name' => $tehsilName]);

                foreach ($villages as $villageName) {
                    Village::query()->firstOrCreate(['tehsil_id' => $tehsil->id, 'name' => $villageName]);
                }
            }
        }
    }
}
