<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_type' => fake()->randomElement(array_keys(\App\Models\InventoryItem::ITEM_TYPES)),
            'name' => fake()->unique()->words(3, true),
            'brand' => fake()->company(),
            'manufacturer' => fake()->company(),
            'specification' => fake()->sentence(),
            'uom' => fake()->randomElement(['mL', 'g', 'pack', 'pcs']),
            'pack_size' => fake()->randomFloat(2, 1, 1000),
            'is_hazardous' => fake()->boolean(20),
            'hazard_class' => fake()->optional()->word(),
            'storage_condition' => fake()->randomElement(array_keys(\App\Models\InventoryItem::STORAGE_CONDITIONS)),
            'min_stock' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }
}
