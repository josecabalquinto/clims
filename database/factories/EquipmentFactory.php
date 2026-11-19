<?php

namespace Database\Factories;

use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Equipment> */
class EquipmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'category_id' => EquipmentCategoryFactory::new(),
            'location_id' => EquipmentLocationFactory::new(),
            'asset_tag' => fake()->unique()->bothify('CLIMS-########'),
            'name' => 'Laboratory equipment',
            'quantity' => 1,
            'available_quantity' => 1,
            'defective_quantity' => 0,
            'condition' => 'good',
        ];
    }
}
