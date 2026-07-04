<?php

namespace App\Policies;

use App\Models\MessageTemplate;
use App\Models\OrganizationMember;
use App\Models\User;

class MessageTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MessageTemplate $messageTemplate): bool
    {
        return (bool) $user->activeMembershipFor($messageTemplate->organization);
    }

    public function create(User $user): bool
    {
        $membership = $user->activeMembership();

        return $membership && $membership->role !== OrganizationMember::ROLE_READONLY;
    }

    public function update(User $user, MessageTemplate $messageTemplate): bool
    {
        $membership = $user->activeMembershipFor($messageTemplate->organization);

        return $membership && $membership->role !== OrganizationMember::ROLE_READONLY;
    }
}
