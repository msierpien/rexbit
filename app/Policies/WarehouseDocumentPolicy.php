<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WarehouseDocument;

class WarehouseDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WarehouseDocument $document): bool
    {
        return $user->id === $document->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WarehouseDocument $document): bool
    {
        return $user->id === $document->user_id && $document->status !== 'posted';
    }

    public function delete(User $user, WarehouseDocument $document): bool
    {
        return $user->id === $document->user_id && $document->status === 'draft';
    }
}
