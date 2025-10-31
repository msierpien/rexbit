<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WarehouseLocation;

class WarehouseLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WarehouseLocation $location): bool
    {
        return $user->id === $location->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WarehouseLocation $location): bool
    {
        return $user->id === $location->user_id;
    }

    public function delete(User $user, WarehouseLocation $location): bool
    {
        return $user->id === $location->user_id && ! $location->is_default;
    }
}
