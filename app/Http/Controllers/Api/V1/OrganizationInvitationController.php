<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizations\AcceptOrganizationInvitation;
use App\Actions\Organizations\InviteOrganizationMember;
use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrganizationInvitationRequest;
use App\Http\Resources\OrganizationInvitationResource;
use App\Http\Resources\OrganizationMemberResource;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class OrganizationInvitationController extends Controller
{
    public function index(Request $request, OrganizationContext $organizationContext): AnonymousResourceCollection
    {
        $invitations = OrganizationInvitation::query()
            ->whereBelongsTo($organizationContext->organization())
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return OrganizationInvitationResource::collection($invitations);
    }

    public function store(
        StoreOrganizationInvitationRequest $request,
        OrganizationContext $organizationContext,
        InviteOrganizationMember $invite,
        RecordAuditLog $auditLog,
    ): JsonResponse {
        $organization = $organizationContext->organization();

        abort_unless($organizationContext->membership()?->isAdmin(), Response::HTTP_FORBIDDEN);

        $existingMember = OrganizationMember::query()
            ->whereBelongsTo($organization)
            ->whereHas('user', fn ($query) => $query->where('email', $request->validated('email')))
            ->exists();

        if ($existingMember) {
            return response()->json([
                'message' => 'This user already belongs to the active organization.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $invitation = $invite->execute($organization, $request->user(), $request->validated());

        $auditLog->execute('organization_invitation.created', $request->user(), $organization, $invitation, request: $request);

        return (new OrganizationInvitationResource($invitation))
            ->response()
            ->setStatusCode(201);
    }

    public function accept(
        string $token,
        Request $request,
        AcceptOrganizationInvitation $accept,
        RecordAuditLog $auditLog,
    ): JsonResponse {
        $invitation = OrganizationInvitation::query()
            ->where('token', $token)
            ->firstOrFail();

        if (! $invitation->isPending() || $invitation->isExpired()) {
            return response()->json([
                'message' => 'This invitation is no longer valid.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (mb_strtolower($request->user()->email) !== mb_strtolower($invitation->email)) {
            return response()->json([
                'message' => 'This invitation belongs to another email address.',
            ], Response::HTTP_FORBIDDEN);
        }

        $member = $accept->execute($invitation, $request->user());

        $auditLog->execute('organization_invitation.accepted', $request->user(), $invitation->organization, $invitation, request: $request);

        return (new OrganizationMemberResource($member->load('user')))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(OrganizationInvitation $organizationInvitation, Request $request, RecordAuditLog $auditLog): Response
    {
        Gate::authorize('delete', $organizationInvitation);

        if (! $organizationInvitation->isPending()) {
            return response()->json([
                'message' => 'Only pending invitations can be cancelled.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $organizationInvitation->update([
            'status' => OrganizationInvitation::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $auditLog->execute('organization_invitation.cancelled', $request->user(), $organizationInvitation->organization, $organizationInvitation, request: $request);

        return response()->noContent();
    }
}
