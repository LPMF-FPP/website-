<?php

namespace Database\Factories;

use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        return [
            'item_id' => \App\Models\InventoryItem::factory(),
            'movement_type' => 'ISSUE',
            'qty' => $this->faker->numberBetween(1, 100),
            'uom' => 'PCS',
            'performed_at' => now(),
            'performed_by' => \App\Models\User::factory(),
        ];
    }
}
