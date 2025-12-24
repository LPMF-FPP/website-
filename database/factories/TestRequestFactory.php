<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TestRequest>
 */
class TestRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $submittedAt = fake()->dateTimeBetween('-6 months', 'now');
        $uniqueSuffix = fake()->unique()->numerify('####');
        
        return [
            // Generate unique numbers to avoid constraint violations in tests
            'request_number' => now()->format('Y-m-d') . '-' . $uniqueSuffix,
            'receipt_number' => now()->format('Y-m-d') . '-' . $uniqueSuffix,
            'investigator_id' => \App\Models\Investigator::factory(),
            'user_id' => \App\Models\User::factory(),
            'to_office' => fake()->randomElement(['Pusdokkes Polri', 'Labfor Polri', 'Puslabfor Bareskrim']),
            'suspect_name' => fake()->name(),
            'suspect_gender' => fake()->randomElement(['Laki-laki', 'Perempuan']),
            'suspect_age' => fake()->numberBetween(17, 65),
            'suspect_address' => fake()->address(),
            'case_number' => fake()->bothify('BP/#????/###/????/???'),
            'case_description' => fake()->paragraph(),
            'incident_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'incident_location' => fake()->address(),
            'status' => fake()->randomElement(['submitted', 'verified', 'received', 'in_testing', 'analysis', 'quality_check', 'ready_for_delivery', 'completed']),
            'official_letter_path' => null,
            'evidence_photo_path' => null,
            'submitted_at' => $submittedAt,
            'verified_at' => null,
            'received_at' => null,
            'completed_at' => null,
        ];
    }
}
