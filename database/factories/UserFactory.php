<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'title_prefix' => fake()->optional(0.3)->randomElement(['Dr.', 'Ir.', 'Apt.']),
            'title_suffix' => fake()->optional(0.4)->randomElement(['M.Si', 'M.Kes', 'Sp.FK']),
            'rank' => fake()->optional(0.5)->randomElement(['AKP', 'AKBP', 'Kompol', 'Penata Tk. I']),
            'nrp' => fake()->optional(0.6)->numerify('#########'),
            'nip' => fake()->optional(0.6)->numerify('19###########'),
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
}
