<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sample>
 */
class SampleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_request_id' => \App\Models\TestRequest::factory(),
            'sample_code' => 'SAMP-'.fake()->unique()->numerify('#####'),
            'short_description' => fake()->word(),
            'sample_description' => fake()->sentence(),
            'sample_form' => fake()->randomElement(['powder', 'pill', 'liquid', 'plant', 'crystal', 'paste', 'capsule', 'other']),
            'sample_category' => fake()->randomElement(['narkotika', 'prekursor', 'zat_adiktif', 'obat_keras', 'other']),
            'sample_color' => fake()->safeColorName(),
            'sample_weight' => fake()->randomFloat(2, 0.1, 1000),
            'package_quantity' => fake()->numberBetween(1, 10),
            'unit' => fake()->randomElement(['tablet', 'kapsul', 'serbuk', 'ml', 'gram']),
            'condition' => fake()->randomElement(['baik', 'rusak', 'basah', 'kering']),
            'sample_status' => fake()->randomElement(['received', 'in_queue', 'in_testing', 'tested']),
        ];
    }
}
