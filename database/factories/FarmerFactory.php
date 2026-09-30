<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Farmer;
use App\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Farmer>
 */
class FarmerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farmer_no' => 'FRM'.fake()->unique()->numerify('######'),
            'branch_id' => fn () => Branch::query()->value('id'),
            'name' => fake()->name(),
            'father_name' => fake()->name('male'),
            'mobile' => fake()->unique()->numerify('9#########'),
            'village_id' => fn () => Village::query()->inRandomOrder()->value('id'),
            'land_acres' => fake()->numberBetween(2, 50),
        ];
    }
}
