<?php

namespace Database\Factories;

use App\Enums\BorrowerType;
use App\Enums\BorrowStatus;
use App\Models\BorrowTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BorrowTransaction> */
class BorrowTransactionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'borrower_name' => fake()->name(),
            'borrower_type' => BorrowerType::Student,
            'borrowed_at' => now(),
            'expected_return_at' => now()->addDays(7),
            'status' => BorrowStatus::Borrowed,
            'recorded_by' => UserFactory::new(),
        ];
    }
}
