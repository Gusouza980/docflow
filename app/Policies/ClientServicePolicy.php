<?php

namespace App\Policies;

use App\Models\ClientService;
use App\Models\OrganizationMember;
use App\Models\User;

class ClientServicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ClientService $clientService): bool
    {
        return app(ClientPolicy::class)->view($user, $clientService->client);
    }

    public function create(User $user): bool
    {
        $membership = $user->activeMembership();

        return $membership && $membership->role !== OrganizationMember::ROLE_READONLY;
    }

    public function update(User $user, ClientService $clientService): bool
    {
        return app(ClientPolicy::class)->update($user, $clientService->client);
    }
}
