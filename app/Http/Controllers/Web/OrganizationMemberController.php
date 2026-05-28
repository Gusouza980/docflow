<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class OrganizationMemberController extends Controller
{
    public function index(Request $request, WebOrganizationContext $webOrganizationContext): InertiaResponse|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Crie ou aceite uma organização para gerenciar a equipe.');
        }

        $organization = $membership->organization;
        $canManage = $membership->isAdmin();

        $members = OrganizationMember::query()
            ->with('user')
            ->whereBelongsTo($organization)
            ->latest()
            ->get()
            ->map(fn (OrganizationMember $member): array => [
                'id' => $member->id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => $member->role,
                'status' => $member->status,
                'joined_at' => $member->joined_at?->toISOString(),
                'suspended_at' => $member->suspended_at?->toISOString(),
                'can_suspend' => $canManage && $member->isActive(),
                'can_reactivate' => $canManage && ! $member->isActive(),
            ]);

        $invitations = OrganizationInvitation::query()
            ->whereBelongsTo($organization)
            ->latest()
            ->get()
            ->map(fn (OrganizationInvitation $invitation): array => [
                'id' => $invitation->id,
                'name' => $invitation->name,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'status' => $invitation->status,
                'expires_at' => $invitation->expires_at?->toISOString(),
                'can_cancel' => $canManage && $invitation->isPending(),
            ]);

        return Inertia::render('Team/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'members' => $members,
            'invitations' => $invitations,
            'canManage' => $canManage,
        ]);
    }

    public function suspend(
        OrganizationMember $organizationMember,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $this->authorizeActiveOrganizationMember($request, $webOrganizationContext, $organizationMember);

        if ($organizationMember->isAdmin() && $this->activeAdminCount($organizationMember) <= 1) {
            return redirect()->back()->withErrors([
                'member' => 'O último administrador ativo não pode ser suspenso.',
            ]);
        }

        $organizationMember->update([
            'status' => OrganizationMember::STATUS_SUSPENDED,
            'suspended_at' => now(),
        ]);

        $organizationMember->user->tokens()->delete();

        $auditLog->execute('web.organization_member.suspended', $request->user(), $membership->organization, $organizationMember, request: $request);

        return redirect()->route('team.index')->with('status', 'Membro suspenso.');
    }

    public function reactivate(
        OrganizationMember $organizationMember,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $this->authorizeActiveOrganizationMember($request, $webOrganizationContext, $organizationMember);

        $organizationMember->update([
            'status' => OrganizationMember::STATUS_ACTIVE,
            'suspended_at' => null,
        ]);

        $auditLog->execute('web.organization_member.reactivated', $request->user(), $membership->organization, $organizationMember, request: $request);

        return redirect()->route('team.index')->with('status', 'Membro reativado.');
    }

    private function authorizeActiveOrganizationMember(
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        OrganizationMember $organizationMember,
    ): OrganizationMember {
        $membership = $webOrganizationContext->membership($request);

        abort_unless($membership, Response::HTTP_NOT_FOUND);
        abort_unless($membership->organization_id === $organizationMember->organization_id, Response::HTTP_NOT_FOUND);
        abort_unless($membership->isAdmin(), Response::HTTP_FORBIDDEN);

        return $membership;
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
