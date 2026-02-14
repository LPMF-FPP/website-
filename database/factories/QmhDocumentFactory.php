<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class QmhDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'doc_code' => 'QMH-TEST-'.$this->faker->unique()->numberBetween(100, 999),
            'title' => $this->faker->sentence(3),
            'clause' => $this->faker->randomElement([4, 5, 6, 7, 8]),
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ];
    }
}
