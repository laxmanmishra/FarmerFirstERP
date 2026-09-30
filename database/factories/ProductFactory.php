<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'product_type' => ProductType::Tractor,
            'name' => fake()->unique()->bothify('## DI ??'),
            'hp' => fake()->numberBetween(25, 75),
        ];
    }
}
