<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Models\User;

class EquipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Equipment $equipment): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, Equipment $equipment): bool
    {
        return $user->is_active;
    }

    public function delete(User $user, Equipment $equipment): bool
    {
        return $user->is_active && ! $equipment->borrowItems()->exists();
    }
}
