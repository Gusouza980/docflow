<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\OrganizationMember;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return (bool) $user->activeMembershipFor($announcement->organization);
    }

    public function create(User $user): bool
    {
        $membership = $user->activeMembership();

        return $membership && $membership->role !== OrganizationMember::ROLE_READONLY;
    }

    public function update(User $user, Announcement $announcement): bool
    {
        $membership = $user->activeMembershipFor($announcement->organization);

        return $membership && $membership->role !== OrganizationMember::ROLE_READONLY;
    }
}
