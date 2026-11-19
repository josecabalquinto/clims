<?php

namespace Database\Factories;

use App\Models\BorrowReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BorrowReturn> */
class BorrowReturnFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'borrow_item_id' => BorrowItemFactory::new()->state([
                'returned_quantity' => 1,
                'equipment_id' => EquipmentFactory::new(),
            ]),
            'quantity' => 1,
            'defective_quantity' => 0,
            'recorded_by' => UserFactory::new(),
            'returned_at' => now(),
        ];
    }
}
