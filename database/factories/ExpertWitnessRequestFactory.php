<?php

namespace Database\Factories;

use App\Models\ExpertWitnessRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpertWitnessRequestFactory extends Factory
{
    protected $model = ExpertWitnessRequest::class;

    public function definition(): array
    {
        return [
            'source' => ExpertWitnessRequest::SOURCE_EXTERNAL,
            'submitted_by' => User::factory(),
            'letter_number' => 'B/'.$this->faker->unique()->numerify('####').'/IX/2026',
            'letter_date' => now()->toDateString(),
            'investigator_name' => $this->faker->name(),
            'investigator_institution' => $this->faker->company(),
            'investigator_phone' => $this->faker->numerify('08##########'),
            'submitted_at' => now(),
        ];
    }
}
