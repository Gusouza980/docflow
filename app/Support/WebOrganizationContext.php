<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Http\Request;

class WebOrganizationContext
{
    public function __construct(private OrganizationContext $organizationContext) {}

    public function membership(Request $request): ?OrganizationMember
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $membership = $this->resolveMembership($request, $user);

        if ($membership) {
            setPermissionsTeamId($membership->organization_id);
            $user->unsetRelation('roles')->unsetRelation('permissions');

            $request->attributes->set('organization', $membership->organization);
            $request->attributes->set('organization_member', $membership);
            $this->organizationContext->set($membership->organization, $membership);
        }

        return $membership;
    }

    private function resolveMembership(Request $request, User $user): ?OrganizationMember
    {
        $memberships = $user->activeOrganizationMemberships()
            ->with('organization')
            ->whereHas('organization', fn ($query) => $query->where('status', Organization::STATUS_ACTIVE))
            ->oldest()
            ->get();

        if ($memberships->isEmpty()) {
            $request->session()->forget('active_organization_id');

            return null;
        }

        $activeOrganizationId = $request->session()->get('active_organization_id');
        $membership = $memberships->firstWhere('organization_id', $activeOrganizationId) ?? $memberships->first();

        $request->session()->put('active_organization_id', $membership->organization_id);

        return $membership;
    }
}
