<?php

namespace App\Policies;

use App\Models\ProductCatalog;
use App\Models\User;

class ProductCatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductCatalog $catalog): bool
    {
        return $catalog->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ProductCatalog $catalog): bool
    {
        return $catalog->user_id === $user->id;
    }

    public function delete(User $user, ProductCatalog $catalog): bool
    {
        return $catalog->user_id === $user->id;
    }
}
