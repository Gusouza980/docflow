<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\InviteOrganizationMember;
use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreOrganizationInvitationRequest;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrganizationInvitationController extends Controller
{
    public function store(
        StoreOrganizationInvitationRequest $request,
        WebOrganizationContext $webOrganizationContext,
        InviteOrganizationMember $invite,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership?->isAdmin(), Response::HTTP_FORBIDDEN);

        $organization = $membership->organization;
        $email = mb_strtolower($request->validated('email'));

        $existingMember = OrganizationMember::query()
            ->whereBelongsTo($organization)
            ->whereHas('user', fn ($query) => $query->where('email', $email))
            ->exists();

        if ($existingMember) {
            return redirect()->back()->withErrors([
                'email' => 'Este usuário já pertence à organização ativa.',
            ]);
        }

        $existingInvitation = OrganizationInvitation::query()
            ->whereBelongsTo($organization)
            ->where('email', $email)
            ->where('status', OrganizationInvitation::STATUS_PENDING)
            ->exists();

        if ($existingInvitation) {
            return redirect()->back()->withErrors([
                'email' => 'Já existe um convite pendente para este e-mail.',
            ]);
        }

        $invitation = $invite->execute($organization, $request->user(), $request->validated());

        $auditLog->execute('web.organization_invitation.created', $request->user(), $organization, $invitation, request: $request);

        return redirect()->route('team.index')->with('status', 'Convite criado.');
    }

    public function destroy(
        OrganizationInvitation $organizationInvitation,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, Response::HTTP_NOT_FOUND);
        abort_unless($membership->organization_id === $organizationInvitation->organization_id, Response::HTTP_NOT_FOUND);
        abort_unless($membership->isAdmin(), Response::HTTP_FORBIDDEN);

        if (! $organizationInvitation->isPending()) {
            return redirect()->back()->withErrors([
                'invitation' => 'Apenas convites pendentes podem ser cancelados.',
            ]);
        }

        $organizationInvitation->update([
            'status' => OrganizationInvitation::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $auditLog->execute('web.organization_invitation.cancelled', $request->user(), $membership->organization, $organizationInvitation, request: $request);

        return redirect()->route('team.index')->with('status', 'Convite cancelado.');
    }
}
