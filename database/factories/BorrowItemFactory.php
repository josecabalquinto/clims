<?php

namespace Database\Factories;

use App\Models\BorrowItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BorrowItem> */
class BorrowItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'borrow_transaction_id' => BorrowTransactionFactory::new(),
            'equipment_id' => EquipmentFactory::new()->state(['available_quantity' => 0]),
            'quantity' => 1,
            'returned_quantity' => 0,
        ];
    }
}
