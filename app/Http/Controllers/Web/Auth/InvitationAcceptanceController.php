<?php

namespace App\Http\Controllers\Web\Auth;

use App\Actions\Organizations\AcceptOrganizationInvitation;
use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Models\OrganizationInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvitationAcceptanceController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = OrganizationInvitation::query()
            ->with('organization')
            ->where('token', $token)
            ->firstOrFail();

        return Inertia::render('Auth/AcceptInvitation', [
            'invitation' => [
                'token' => $invitation->token,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'status' => $invitation->status,
                'expires_at' => $invitation->expires_at?->toISOString(),
                'organization' => [
                    'name' => $invitation->organization?->name,
                ],
                'can_accept' => $invitation->isPending() && ! $invitation->isExpired(),
            ],
        ]);
    }

    public function store(
        string $token,
        Request $request,
        AcceptOrganizationInvitation $accept,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $invitation = OrganizationInvitation::query()
            ->where('token', $token)
            ->firstOrFail();

        abort_unless($invitation->isPending() && ! $invitation->isExpired(), HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        abort_unless(mb_strtolower($request->user()->email) === mb_strtolower($invitation->email), HttpResponse::HTTP_FORBIDDEN);

        $member = $accept->execute($invitation, $request->user());

        $auditLog->execute('web.organization_invitation.accepted', $request->user(), $invitation->organization, $invitation, request: $request);

        return redirect()->route('dashboard')->with('status', "Convite para {$member->organization->name} aceito com sucesso.");
    }
}
