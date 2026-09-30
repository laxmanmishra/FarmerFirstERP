<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_code' => 'EMP'.fake()->unique()->numerify('######'),
            'name' => fake()->name(),
            'mobile' => fake()->numerify('9#########'),
            'email' => fake()->safeEmail(),
            'date_of_joining' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
