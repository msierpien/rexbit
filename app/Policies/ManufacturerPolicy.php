<?php

namespace App\Policies;

use App\Models\Manufacturer;
use App\Models\User;

class ManufacturerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Manufacturer $manufacturer): bool
    {
        return $manufacturer->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Manufacturer $manufacturer): bool
    {
        return $manufacturer->user_id === $user->id;
    }

    public function delete(User $user, Manufacturer $manufacturer): bool
    {
        return $manufacturer->user_id === $user->id;
    }
}
