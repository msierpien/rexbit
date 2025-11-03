<?php

namespace App\Policies;

use App\Models\InventoryCount;
use App\Models\User;

class InventoryCountPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InventoryCount $inventoryCount): bool
    {
        return $user->id === $inventoryCount->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InventoryCount $inventoryCount): bool
    {
        return $user->id === $inventoryCount->user_id && 
               $inventoryCount->status->allowsEditing();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InventoryCount $inventoryCount): bool
    {
        return $user->id === $inventoryCount->user_id && 
               $inventoryCount->status->allowsDeletion();
    }

    /**
     * Determine whether the user can start the inventory count.
     */
    public function start(User $user, InventoryCount $inventoryCount): bool
    {
        return $user->id === $inventoryCount->user_id && 
               $inventoryCount->status->canBeStarted();
    }

    /**
     * Determine whether the user can complete the inventory count.
     */
    public function complete(User $user, InventoryCount $inventoryCount): bool
    {
        return $user->id === $inventoryCount->user_id && 
               $inventoryCount->canBeCompleted();
    }

    /**
     * Determine whether the user can approve the inventory count.
     */
    public function approve(User $user, InventoryCount $inventoryCount): bool
    {
        return $user->id === $inventoryCount->user_id && 
               $inventoryCount->canBeApproved();
    }

    /**
     * Determine whether the user can cancel the inventory count.
     */
    public function cancel(User $user, InventoryCount $inventoryCount): bool
    {
        return $user->id === $inventoryCount->user_id && 
               $inventoryCount->status->canBeCancelled();
    }

    /**
     * Determine whether the user can update quantities in the inventory count.
     */
    public function updateQuantity(User $user, InventoryCount $inventoryCount): bool
    {
        return $user->id === $inventoryCount->user_id && 
               $inventoryCount->status->allowsEditing();
    }
}
