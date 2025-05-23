<?php

namespace App\Policies;

use App\Models\Token;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TokenPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'token_read');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Token $token): bool
    {
        return $user->hasPermissionTo(permission: 'token_read');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(permission: 'token_create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Token $token): bool
    {
        return $user->hasPermissionTo(permission: 'token_update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Token $token): bool
    {
        return $user->hasPermissionTo(permission: 'token_delete');
    }
}
