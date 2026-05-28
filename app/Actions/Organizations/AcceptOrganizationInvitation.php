<?php

namespace App\Actions\Organizations;

use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class AcceptOrganizationInvitation
{
    public function execute(OrganizationInvitation $invitation, User $user): OrganizationMember
    {
        return DB::transaction(function () use ($invitation, $user): OrganizationMember {
            $member = OrganizationMember::updateOrCreate(
                [
                    'organization_id' => $invitation->organization_id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $invitation->role,
                    'status' => OrganizationMember::STATUS_ACTIVE,
                    'joined_at' => now(),
                    'suspended_at' => null,
                ],
            );

            $invitation->update([
                'accepted_by_user_id' => $user->id,
                'status' => OrganizationInvitation::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);

            setPermissionsTeamId($invitation->organization_id);

            $role = Role::findOrCreate($invitation->role, 'web');
            $user->assignRole($role);
            $user->unsetRelation('roles')->unsetRelation('permissions');

            return $member;
        });
    }
}
