<?php

namespace App\Policies;

use App\Models\ClientContact;
use App\Models\OrganizationMember;
use App\Models\User;

class ClientContactPolicy
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
    public function view(User $user, ClientContact $clientContact): bool
    {
        return $user->can('view', $clientContact->client);
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
    public function update(User $user, ClientContact $clientContact): bool
    {
        $membership = $user->activeMembershipFor($clientContact->client->organization);

        return $membership?->role !== OrganizationMember::ROLE_READONLY
            && $user->can('view', $clientContact->client);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ClientContact $clientContact): bool
    {
        return $this->update($user, $clientContact);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ClientContact $clientContact): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ClientContact $clientContact): bool
    {
        return false;
    }
}
