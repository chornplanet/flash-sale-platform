<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => sprintf('SKU-%s-%05d', Str::upper(Str::random(4)), fake()->unique()->numberBetween(1, 99999)),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'stock_count' => fake()->numberBetween(100, 10000),
            'reserved_count' => 0,
            'price' => fake()->randomFloat(2, 50, 5000),
            'is_active' => true,
        ];
    }
}
