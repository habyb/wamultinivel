<?php

namespace App\Policies;

use App\Models\SentMessage;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SentMessagePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'sentmessage_read');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SentMessage $sentModel): bool
    {
        return $user->hasPermissionTo(permission: 'sentmessage_read');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'sentmessage_create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SentMessage $sentModel): bool
    {
        return $user->hasPermissionTo(permission: 'sentmessage_update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SentMessage $sentModel): bool
    {
        return $user->hasPermissionTo(permission: 'sentmessage_delete');
    }
}
