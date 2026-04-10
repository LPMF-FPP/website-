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
            'witness_entries' => null,
            'notes' => fake()->optional()->sentence(),
            'executed_by' => User::factory(),
            'executed_by_name' => fake()->name(),
            'executed_by_role' => fake()->randomElement(['Analis', 'Koordinator', 'Penyelia']),
            'executed_by_identity' => 'NRP: '.fake()->numerify('########'),
            'approver_name' => fake()->name(),
            'approver_role' => 'Kepala Farmapol',
            'approver_identity' => 'NRP: '.fake()->numerify('########'),
            'documentation_photos' => null,
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
                'witness_entries' => [[
                    'name' => trim((string) ($witness->display_name_with_title ?: $witness->name ?: '-')),
                    'role' => trim((string) ($witness->rank ?? $witness->role ?? '-')),
                    'identity' => trim((string) ($witness->nrp ?: $witness->nip ?: '')),
                    'user_id' => $witness->id,
                ]],
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
