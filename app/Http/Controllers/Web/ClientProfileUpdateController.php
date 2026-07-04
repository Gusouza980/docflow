<?php

namespace App\Http\Controllers\Web;

use App\Actions\ClientProfileUpdates\ReviewClientProfileUpdate;
use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ReviewClientProfileUpdateRequest;
use App\Models\ClientProfileUpdateRequest;
use App\Models\OrganizationMember;
use App\Support\DisplayFormat;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ClientProfileUpdateController extends Controller
{
    public function approve(
        ClientProfileUpdateRequest $profileUpdate,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        ReviewClientProfileUpdate $reviewClientProfileUpdate,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        abort_if($profileUpdate->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $profileUpdate->client);

        $reviewClientProfileUpdate->approve($profileUpdate, $request->user());

        $auditLog->execute(
            'web.client_profile_update.approved',
            $request->user(),
            $membership->organization,
            $profileUpdate,
            request: $request,
        );

        return redirect()->route('portal.index')->with('status', 'Alteração de perfil aprovada.');
    }

    public function reject(
        ClientProfileUpdateRequest $profileUpdate,
        ReviewClientProfileUpdateRequest $formRequest,
        WebOrganizationContext $webOrganizationContext,
        ReviewClientProfileUpdate $reviewClientProfileUpdate,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $this->membership($formRequest, $webOrganizationContext);
        abort_if($profileUpdate->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $profileUpdate->client);

        $reviewClientProfileUpdate->reject(
            $profileUpdate,
            $formRequest->user(),
            $formRequest->validated('review_notes'),
        );

        $auditLog->execute(
            'web.client_profile_update.rejected',
            $formRequest->user(),
            $membership->organization,
            $profileUpdate,
            request: $formRequest,
        );

        return redirect()->route('portal.index')->with('status', 'Alteração de perfil rejeitada.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function pendingSummaries(int $organizationId): array
    {
        return ClientProfileUpdateRequest::query()
            ->with(['client', 'portalAccess'])
            ->where('organization_id', $organizationId)
            ->where('status', ClientProfileUpdateRequest::STATUS_PENDING)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (ClientProfileUpdateRequest $update): array => [
                'id' => $update->id,
                'client' => [
                    'id' => $update->client->id,
                    'name' => $update->client->display_name,
                ],
                'contact' => [
                    'name' => $update->portalAccess->name,
                    'email' => $update->portalAccess->email,
                ],
                'changes' => $update->changes,
                'created_at' => DisplayFormat::dateTime($update->created_at),
            ])
            ->values()
            ->all();
    }

    private function membership(Request $request, WebOrganizationContext $webOrganizationContext): OrganizationMember
    {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($membership->role === OrganizationMember::ROLE_READONLY, HttpResponse::HTTP_FORBIDDEN);

        return $membership;
    }
}
