<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $createdAt = fake()->dateTimeBetween('-18 months', '-1 week');
        $emailVerifiedAt = fake()->boolean(85)
            ? fake()->dateTimeBetween($createdAt, 'now')
            : null;

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->freeEmail(),
            'email_verified_at' => $emailVerifiedAt,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => fake()->boolean(35) ? Str::random(10) : null,
            'created_at' => $createdAt,
            'updated_at' => fake()->dateTimeBetween($createdAt, 'now'),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => fake()->dateTimeBetween(
                $attributes['created_at'] ?? '-18 months',
                'now'
            ),
        ]);
    }

    /**
     * Indicate that the user is a recently registered customer.
     */
    public function recent(): static
    {
        return $this->state(function (array $attributes) {
            $createdAt = fake()->dateTimeBetween('-30 days', 'now');

            return [
                'created_at' => $createdAt,
                'updated_at' => fake()->dateTimeBetween($createdAt, 'now'),
                'email_verified_at' => fake()->boolean(75)
                    ? fake()->dateTimeBetween($createdAt, 'now')
                    : null,
            ];
        });
    }
}
