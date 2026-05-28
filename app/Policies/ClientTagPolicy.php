<?php

namespace App\Policies;

use App\Models\ClientTag;
use App\Models\User;

class ClientTagPolicy
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
    public function view(User $user, ClientTag $clientTag): bool
    {
        return $user->activeMembershipFor($clientTag->organization) !== null;
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
    public function update(User $user, ClientTag $clientTag): bool
    {
        return $user->activeMembershipFor($clientTag->organization)?->isAdmin()
            || $user->activeMembershipFor($clientTag->organization)?->isManager();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ClientTag $clientTag): bool
    {
        return $this->update($user, $clientTag);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ClientTag $clientTag): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ClientTag $clientTag): bool
    {
        return false;
    }
}
