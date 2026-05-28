<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\OrganizationMember;
use App\Models\User;

class ClientPolicy
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
    public function view(User $user, Client $client): bool
    {
        return $this->canAccessClient($user, $client);
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
    public function update(User $user, Client $client): bool
    {
        $membership = $user->activeMembershipFor($client->organization);

        if (! $membership || $membership->role === OrganizationMember::ROLE_READONLY) {
            return false;
        }

        return $this->canAccessClient($user, $client);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Client $client): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Client $client): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Client $client): bool
    {
        return false;
    }

    private function canAccessClient(User $user, Client $client): bool
    {
        $membership = $user->activeMembershipFor($client->organization);

        if (! $membership) {
            return false;
        }

        if ($membership->isAdmin() || $membership->isManager()) {
            return true;
        }

        if ($client->access_policy === Client::ACCESS_ALL_MEMBERS) {
            return true;
        }

        return $client->responsibles()->whereKey($membership->id)->exists()
            || $client->accessMembers()->whereKey($membership->id)->exists();
    }
}
