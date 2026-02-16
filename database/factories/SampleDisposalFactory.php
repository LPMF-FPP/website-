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
            'witness_user_id' => null,
            'notes' => fake()->optional()->sentence(),
            'executed_by' => User::factory(),
            'executed_by_name' => fake()->name(),
            'executed_by_role' => fake()->randomElement(['Analis', 'Koordinator', 'Penyelia']),
            'created_by' => User::factory(),
        ];
    }

    public function withWitnessUser(?User $user = null): static
    {
        return $this->state(function () use ($user) {
            $witness = $user ?? User::factory()->create();

            return [
                'witness_user_id' => $witness->id,
                'witness_name' => trim((string) ($witness->display_name_with_title ?: $witness->name ?: '-')),
                'witness_role' => trim((string) ($witness->rank ?? $witness->role ?? '-')),
            ];
        });
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
