<?php

namespace Database\Factories;

use App\Models\InventoryBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryBalanceFactory extends Factory
{
    protected $model = InventoryBalance::class;

    public function definition(): array
    {
        return [
            'item_id' => \App\Models\InventoryItem::factory(),
            'location_id' => \App\Models\InventoryLocation::factory(),
            'on_hand_qty' => $this->faker->numberBetween(0, 100),
            'reserved_qty' => 0,
            'updated_at' => now(),
        ];
    }
}
