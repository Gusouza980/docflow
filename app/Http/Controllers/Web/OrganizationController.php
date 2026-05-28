<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\CreateOrganization;
use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreOrganizationRequest;
use App\Http\Requests\Web\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request, WebOrganizationContext $webOrganizationContext): Response
    {
        $activeMembership = $webOrganizationContext->membership($request);

        $organizations = Organization::query()
            ->whereHas('members', fn ($query) => $query
                ->whereBelongsTo($request->user())
                ->where('status', OrganizationMember::STATUS_ACTIVE))
            ->withCount([
                'members',
                'invitations as pending_invitations_count' => fn ($query) => $query->where('status', OrganizationInvitation::STATUS_PENDING),
            ])
            ->latest()
            ->get()
            ->map(fn (Organization $organization): array => [
                'id' => $organization->id,
                'name' => $organization->name,
                'document' => $organization->document,
                'email' => $organization->email,
                'phone' => $organization->phone,
                'timezone' => $organization->timezone,
                'status' => $organization->status,
                'members_count' => $organization->members_count,
                'pending_invitations_count' => $organization->pending_invitations_count,
                'active' => $activeMembership?->organization_id === $organization->id,
                'can_update' => $request->user()->can('update', $organization),
            ]);

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
            'activeOrganizationId' => $activeMembership?->organization_id,
        ]);
    }

    public function store(
        StoreOrganizationRequest $request,
        CreateOrganization $createOrganization,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $organization = $createOrganization->execute($request->user(), $request->validated());

        $request->session()->put('active_organization_id', $organization->id);

        $auditLog->execute('web.organization.created', $request->user(), $organization, $organization, request: $request);

        return redirect()->route('organizations.index')->with('status', 'Organização criada e selecionada.');
    }

    public function update(
        UpdateOrganizationRequest $request,
        Organization $organization,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $before = $organization->only(['name', 'document', 'email', 'phone', 'timezone']);

        $organization->update($request->validated());

        $auditLog->execute('web.organization.updated', $request->user(), $organization, $organization, [
            'before' => $before,
            'after' => $organization->only(['name', 'document', 'email', 'phone', 'timezone']),
        ], $request);

        return redirect()->route('organizations.index')->with('status', 'Dados da organização atualizados.');
    }

    public function switch(Request $request, Organization $organization, RecordAuditLog $auditLog): RedirectResponse
    {
        Gate::authorize('view', $organization);

        $request->session()->put('active_organization_id', $organization->id);

        $auditLog->execute('web.organization.switched', $request->user(), $organization, $organization, request: $request);

        return redirect()->back()->with('status', "Organização ativa alterada para {$organization->name}.");
    }
}
