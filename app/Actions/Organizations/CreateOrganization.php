<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class CreateOrganization
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $owner, array $data): Organization
    {
        return DB::transaction(function () use ($owner, $data): Organization {
            $organization = Organization::create($data);

            OrganizationMember::create([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
                'role' => OrganizationMember::ROLE_ADMIN,
                'status' => OrganizationMember::STATUS_ACTIVE,
                'joined_at' => now(),
            ]);

            setPermissionsTeamId($organization->id);

            $role = Role::findOrCreate(OrganizationMember::ROLE_ADMIN, 'web');
            $owner->assignRole($role);
            $owner->unsetRelation('roles')->unsetRelation('permissions');

            return $organization;
        });
    }
}
