<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationMemberResource;
use App\Models\OrganizationMember;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class OrganizationMemberController extends Controller
{
    public function index(Request $request, OrganizationContext $organizationContext): AnonymousResourceCollection
    {
        $members = OrganizationMember::query()
            ->with('user')
            ->whereBelongsTo($organizationContext->organization())
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return OrganizationMemberResource::collection($members);
    }

    public function suspend(OrganizationMember $organizationMember, Request $request, RecordAuditLog $auditLog): OrganizationMemberResource|JsonResponse
    {
        Gate::authorize('update', $organizationMember);

        if ($organizationMember->isAdmin() && $this->activeAdminCount($organizationMember) <= 1) {
            return response()->json([
                'message' => 'The last active administrator cannot be suspended.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $organizationMember->update([
            'status' => OrganizationMember::STATUS_SUSPENDED,
            'suspended_at' => now(),
        ]);

        $organizationMember->user->tokens()->delete();

        $auditLog->execute('organization_member.suspended', $request->user(), $organizationMember->organization, $organizationMember, request: $request);

        return new OrganizationMemberResource($organizationMember->load('user'));
    }

    public function reactivate(OrganizationMember $organizationMember, Request $request, RecordAuditLog $auditLog): OrganizationMemberResource
    {
        Gate::authorize('update', $organizationMember);

        $organizationMember->update([
            'status' => OrganizationMember::STATUS_ACTIVE,
            'suspended_at' => null,
        ]);

        $auditLog->execute('organization_member.reactivated', $request->user(), $organizationMember->organization, $organizationMember, request: $request);

        return new OrganizationMemberResource($organizationMember->load('user'));
    }

    private function activeAdminCount(OrganizationMember $organizationMember): int
    {
        return OrganizationMember::query()
            ->whereBelongsTo($organizationMember->organization)
            ->where('role', OrganizationMember::ROLE_ADMIN)
            ->where('status', OrganizationMember::STATUS_ACTIVE)
            ->count();
    }
}
