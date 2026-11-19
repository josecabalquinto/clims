<?php

namespace App\Policies;

use App\Models\BorrowTransaction;
use App\Models\User;

class BorrowTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, BorrowTransaction $transaction): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, BorrowTransaction $transaction): bool
    {
        return $user->is_active;
    }

    public function returnEquipment(User $user, BorrowTransaction $transaction): bool
    {
        return $user->is_active;
    }

    public function delete(User $user, BorrowTransaction $transaction): bool
    {
        return false;
    }
}
