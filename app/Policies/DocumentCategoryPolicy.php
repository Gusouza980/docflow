<?php

namespace App\Policies;

use App\Models\DocumentCategory;
use App\Models\OrganizationMember;
use App\Models\User;

class DocumentCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DocumentCategory $documentCategory): bool
    {
        return (bool) $user->activeMembershipFor($documentCategory->organization);
    }

    public function create(User $user): bool
    {
        $membership = $user->activeMembership();

        return $membership && $membership->role !== OrganizationMember::ROLE_READONLY;
    }

    public function update(User $user, DocumentCategory $documentCategory): bool
    {
        $membership = $user->activeMembershipFor($documentCategory->organization);

        return $membership && $membership->role !== OrganizationMember::ROLE_READONLY;
    }

    public function delete(User $user, DocumentCategory $documentCategory): bool
    {
        $membership = $user->activeMembershipFor($documentCategory->organization);

        return $membership && ($membership->isAdmin() || $membership->isManager());
    }
}
