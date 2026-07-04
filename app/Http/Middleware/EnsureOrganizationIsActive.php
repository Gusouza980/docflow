<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Support\Billing\OrganizationAccessibility;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationIsActive
{
    public function __construct(
        private OrganizationContext $organizationContext,
        private OrganizationAccessibility $organizationAccessibility,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organizationId = $request->header('X-Organization-Id');

        if (! $organizationId) {
            return response()->json([
                'message' => 'The active organization is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $organization = Organization::query()
            ->with('subscription')
            ->whereKey($organizationId)
            ->first();

        if (! $organization) {
            return response()->json([
                'message' => 'The active organization was not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $membership = OrganizationMember::query()
            ->whereBelongsTo($organization)
            ->whereBelongsTo($request->user())
            ->where('status', OrganizationMember::STATUS_ACTIVE)
            ->first();

        if (! $membership) {
            return response()->json([
                'message' => 'You do not have access to the active organization.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $this->organizationAccessibility->isAccessible($organization)) {
            $blockReason = $this->organizationAccessibility->blockReason($organization);

            return response()->json([
                'message' => $this->organizationAccessibility->blockMessage($blockReason),
                'code' => 'subscription_inaccessible',
                'reason' => $blockReason,
            ], Response::HTTP_FORBIDDEN);
        }

        setPermissionsTeamId($organization->id);
        $request->user()->unsetRelation('roles')->unsetRelation('permissions');

        $request->attributes->set('organization', $organization);
        $request->attributes->set('organization_member', $membership);
        $this->organizationContext->set($organization, $membership);

        return $next($request);
    }
}
