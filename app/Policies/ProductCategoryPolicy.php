<?php

namespace App\Policies;

use App\Models\ProductCategory;
use App\Models\User;

class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductCategory $category): bool
    {
        return $user->id === $category->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ProductCategory $category): bool
    {
        return $user->id === $category->user_id;
    }

    public function delete(User $user, ProductCategory $category): bool
    {
        return $user->id === $category->user_id;
    }
}
