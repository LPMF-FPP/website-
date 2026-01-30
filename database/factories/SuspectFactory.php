<?php

namespace Database\Factories;

use App\Models\TestRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Suspect>
 */
class SuspectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'test_request_id' => TestRequest::factory(),
            'name' => strtoupper(fake()->name()),
            'gender' => fake()->randomElement(['male', 'female']),
            'age' => fake()->numberBetween(17, 60),
            'order_no' => 1,
        ];
    }
}
