<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;

class InviteOrganizationMember
{
    /**
     * @param  array{name?: string, email: string, role: string}  $data
     */
    public function execute(Organization $organization, User $inviter, array $data): OrganizationInvitation
    {
        return OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'invited_by_user_id' => $inviter->id,
            'name' => $data['name'] ?? null,
            'email' => mb_strtolower($data['email']),
            'role' => $data['role'],
            'expires_at' => now()->addDays(7),
        ]);
    }
}
