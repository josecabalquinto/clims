<?php

namespace App\Policies;

use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use App\Models\User;

class EquipmentReferencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, EquipmentCategory|EquipmentLocation $reference): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, EquipmentCategory|EquipmentLocation $reference): bool
    {
        return $user->is_active;
    }

    public function delete(User $user, EquipmentCategory|EquipmentLocation $reference): bool
    {
        return $user->is_active && ! $reference->equipment()->exists();
    }
}
