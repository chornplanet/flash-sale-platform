<?php

namespace Database\Factories;

use App\Models\SalesEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesEvent>
 */
class SalesEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->subMinutes(fake()->numberBetween(1, 60));

        return [
            'name' => 'Flash Sale '.fake()->words(2, true),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(3),
            'is_active' => true,
        ];
    }
}
