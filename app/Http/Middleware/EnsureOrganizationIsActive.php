<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationIsActive
{
    public function __construct(private OrganizationContext $organizationContext) {}

    /**
     * Handle an incoming request.
     *
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
            ->whereKey($organizationId)
            ->where('status', Organization::STATUS_ACTIVE)
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

        setPermissionsTeamId($organization->id);
        $request->user()->unsetRelation('roles')->unsetRelation('permissions');

        $request->attributes->set('organization', $organization);
        $request->attributes->set('organization_member', $membership);
        $this->organizationContext->set($organization, $membership);

        return $next($request);
    }
}
