<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        $membership = $user->activeMembershipFor($task->organization);

        if (! $membership) {
            return false;
        }

        return ! $task->client || $this->canAccessClient($user, $task->client);
    }

    public function create(User $user): bool
    {
        $membership = $user->activeMembership();

        return $membership && $membership->role !== OrganizationMember::ROLE_READONLY;
    }

    public function update(User $user, Task $task): bool
    {
        $membership = $user->activeMembershipFor($task->organization);

        if (! $membership || $membership->role === OrganizationMember::ROLE_READONLY) {
            return false;
        }

        return ! $task->client || $this->canAccessClient($user, $task->client);
    }

    private function canAccessClient(User $user, Client $client): bool
    {
        return app(ClientPolicy::class)->view($user, $client);
    }
}
