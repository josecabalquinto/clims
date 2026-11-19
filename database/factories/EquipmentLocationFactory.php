<?php

namespace Database\Factories;

use App\Models\EquipmentLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EquipmentLocation> */
class EquipmentLocationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
