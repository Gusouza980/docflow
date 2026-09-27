<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;

class ResolvesOrganizationOwner
{
    public function handle(Organization $organization): ?User
    {
        $member = OrganizationMember::query()
            ->with('user')
            ->where('organization_id', $organization->id)
            ->where('role', OrganizationMember::ROLE_ADMIN)
            ->where('status', OrganizationMember::STATUS_ACTIVE)
            ->orderBy('joined_at')
            ->orderBy('id')
            ->first();

        $user = $member?->user;

        if (! $user || $user->isPlatformAdmin()) {
            return null;
        }

        return $user;
    }
}
