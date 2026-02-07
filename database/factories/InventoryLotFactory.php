<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\InventoryLot;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryLotFactory extends Factory
{
    protected $model = InventoryLot::class;

    public function definition(): array
    {
        return [
            'item_id' => InventoryItem::factory(),
            'lot_no' => $this->faker->bothify('LOT-####'),
            'expiry_date' => $this->faker->dateTimeBetween('now', '+1 year'),
            'received_date' => $this->faker->date(),
            'status' => 'ACTIVE',
            'notes' => $this->faker->sentence(),
        ];
    }
}
