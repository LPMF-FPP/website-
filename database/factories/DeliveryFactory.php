<?php

namespace Database\Factories;

use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Delivery>
 */
class DeliveryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'request_id' => TestRequest::factory(),
            'delivered_by' => User::factory(),
            'status' => \App\Enums\DeliveryStatus::PENDING ?? 'pending',
            'delivery_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'collected_at' => null,
            'has_surat_pengantar' => false,
            'surat_pengantar_number' => null,
            'surat_pengantar_date' => null,
        ];
    }

    public function collected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => \App\Enums\DeliveryStatus::COLLECTED ?? 'collected',
            'collected_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    public function withSuratPengantar(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_surat_pengantar' => true,
            'surat_pengantar_number' => 'B/'.fake()->numerify('###').'/I/2026',
            'surat_pengantar_date' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
