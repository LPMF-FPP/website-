<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SampleDisposalMethod;
use App\Models\SampleDisposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SampleDisposal>
 */
class SampleDisposalFactory extends Factory
{
    protected $model = SampleDisposal::class;

    public function definition(): array
    {
        return [
            'batch_number' => 'DSP-'.fake()->year().'-'.fake()->unique()->numerify('####'),
            'executed_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'method' => fake()->randomElement(SampleDisposalMethod::cases()),
            'witness_name' => fake()->name(),
            'witness_role' => fake()->randomElement(['Kepala Lab', 'Wakil Kepala Lab', 'Koordinator', 'Analis Senior']),
            'notes' => fake()->optional()->sentence(),
            'executed_by' => User::factory(),
            'created_by' => User::factory(),
        ];
    }

    public function bakar(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => SampleDisposalMethod::BAKAR,
        ]);
    }

    public function kubur(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => SampleDisposalMethod::KUBUR,
        ]);
    }

    public function hancur(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => SampleDisposalMethod::HANCUR,
        ]);
    }
}
