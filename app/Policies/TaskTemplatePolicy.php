<?php

namespace App\Policies;

use App\Models\OrganizationMember;
use App\Models\TaskTemplate;
use App\Models\User;

class TaskTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TaskTemplate $taskTemplate): bool
    {
        return (bool) $user->activeMembershipFor($taskTemplate->organization);
    }

    public function create(User $user): bool
    {
        $membership = $user->activeMembership();

        return $membership && $membership->role !== OrganizationMember::ROLE_READONLY;
    }

    public function update(User $user, TaskTemplate $taskTemplate): bool
    {
        $membership = $user->activeMembershipFor($taskTemplate->organization);

        return $membership && $membership->role !== OrganizationMember::ROLE_READONLY;
    }
}
