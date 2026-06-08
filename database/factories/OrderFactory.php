<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_no' => 'ORD-'.now()->format('YmdHis').'-'.fake()->unique()->bothify('????####'),
            'status' => fake()->randomElement(['confirmed', 'confirmed', 'confirmed', 'cancelled', 'failed']),
            'price' => fake()->randomFloat(2, 50, 5000),
            'quantity' => 1,
            'ordered_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
