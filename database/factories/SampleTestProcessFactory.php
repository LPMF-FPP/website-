<?php

namespace Database\Factories;

use App\Enums\TestProcessStage;
use App\Models\Sample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SampleTestProcess>
 */
class SampleTestProcessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sample_id' => Sample::factory(),
            'stage' => fake()->randomElement([
                TestProcessStage::ADMINISTRATION->value,
                TestProcessStage::PREPARATION->value,
                TestProcessStage::INSTRUMENTATION->value,
                TestProcessStage::INTERPRETATION->value,
            ]),
            'metadata' => [],
            'performed_by' => null,
            'completed_at' => null,
        ];
    }

    public function reception(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => TestProcessStage::ADMINISTRATION->value,
        ]);
    }

    public function preparation(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => TestProcessStage::PREPARATION->value,
        ]);
    }

    public function instrumentation(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => TestProcessStage::INSTRUMENTATION->value,
        ]);
    }

    public function interpretation(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => TestProcessStage::INTERPRETATION->value,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
        ]);
    }
}
