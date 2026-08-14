<?php

namespace App\Http\Controllers\Web\Platform;

use App\Actions\Platform\ProvisionTenant;
use App\Actions\Platform\ReactivateOrganization;
use App\Actions\Platform\RecordPlatformAuditLog;
use App\Actions\Platform\SuspendOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Platform\ProvisionTenantRequest;
use App\Http\Requests\Web\Platform\SuspendOrganizationRequest;
use App\Http\Requests\Web\Platform\UpdateOrganizationPlatformNotesRequest;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Support\Billing\OrganizationAccessibility;
use App\Support\Billing\PlanLimitChecker;
use App\Support\Billing\ResolvesOrganizationPlan;
use App\Support\DisplayFormat;
use App\Support\PlatformOrganizationMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());

        $organizations = Organization::query()
            ->withCount([
                'members',
                'clients',
                'activeMembers as active_members_count',
            ])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('document', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Platform/Organizations/Index', [
            'organizations' => [
                'data' => $organizations->getCollection()->map(fn (Organization $organization): array => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'document' => $organization->document,
                    'email' => $organization->email,
                    'status' => $organization->status,
                    'members_count' => $organization->members_count,
                    'active_members_count' => $organization->active_members_count,
                    'clients_count' => $organization->clients_count,
                    'created_at' => DisplayFormat::dateTime($organization->created_at),
                ]),
                'meta' => [
                    'current_page' => $organizations->currentPage(),
                    'last_page' => $organizations->lastPage(),
                    'per_page' => $organizations->perPage(),
                    'total' => $organizations->total(),
                ],
            ],
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'statusOptions' => [
                ['value' => '', 'label' => 'Todos os status'],
                ['value' => Organization::STATUS_ACTIVE, 'label' => 'Ativas'],
                ['value' => Organization::STATUS_SUSPENDED, 'label' => 'Suspensas'],
            ],
            'planOptions' => $this->activePlanOptions(),
        ]);
    }

    public function store(
        ProvisionTenantRequest $request,
        ProvisionTenant $provisionTenant,
    ): RedirectResponse {
        $result = $provisionTenant->execute(
            platformAdmin: $request->user(),
            data: $request->validated(),
            request: $request,
        );

        return redirect()
            ->route('platform.organizations.show', $result['organization'])
            ->with('status', "Cliente {$result['user']->email} provisionado. Enviamos o link para definir a senha.")
            ->with('reset_url', $result['reset_url']);
    }

    public function show(
        Organization $organization,
        Request $request,
        PlatformOrganizationMetrics $platformOrganizationMetrics,
        RecordPlatformAuditLog $recordPlatformAuditLog,
        PlanLimitChecker $planLimitChecker,
        ResolvesOrganizationPlan $resolvesOrganizationPlan,
        OrganizationAccessibility $organizationAccessibility,
    ): Response {
        $recordPlatformAuditLog->execute(
            action: PlatformAuditLog::ACTION_ORGANIZATION_VIEWED,
            platformAdmin: $request->user(),
            subject: $organization,
            request: $request,
        );

        $organization->load(['plan', 'subscription.plan'])->loadCount(['members', 'clients']);

        $members = OrganizationMember::query()
            ->with('user')
            ->where('organization_id', $organization->id)
            ->where('status', OrganizationMember::STATUS_ACTIVE)
            ->orderBy('role')
            ->get()
            ->map(fn (OrganizationMember $member): array => [
                'id' => $member->id,
                'name' => $member->user?->name,
                'email' => $member->user?->email,
                'role' => $member->role,
            ]);

        $recentAuditLogs = PlatformAuditLog::query()
            ->with('platformAdmin')
            ->where('subject_type', $organization->getMorphClass())
            ->where('subject_id', $organization->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (PlatformAuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'admin' => $log->platformAdmin?->name,
                'metadata' => $log->metadata ?? [],
                'created_at' => DisplayFormat::dateTime($log->created_at),
            ]);

        return Inertia::render('Platform/Organizations/Show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'document' => $organization->document,
                'email' => $organization->email,
                'phone' => $organization->phone,
                'timezone' => $organization->timezone,
                'status' => $organization->status,
                'platform_notes' => $organization->platform_notes,
                'plan_id' => $organization->plan_id,
                'members_count' => $organization->members_count,
                'clients_count' => $organization->clients_count,
                'created_at' => DisplayFormat::dateTime($organization->created_at),
            ],
            'planSummary' => $planLimitChecker->usageSummary($organization),
            'planOptions' => $this->activePlanOptions(),
            'activeOverride' => ($activeOverride = $resolvesOrganizationPlan->activeOverrideFor($organization))
                ? [
                    'id' => $activeOverride->id,
                    'reason' => $activeOverride->reason,
                    'limits' => $activeOverride->limits ?? [],
                    'features' => $activeOverride->features ?? [],
                    'expires_at' => DisplayFormat::dateTime($activeOverride->expires_at),
                ]
                : null,
            'metrics' => $platformOrganizationMetrics->for($organization),
            'members' => $members,
            'recentAuditLogs' => $recentAuditLogs,
            'subscription' => $organization->subscription
                ? [
                    'id' => $organization->subscription->id,
                    'status' => $organization->subscription->status,
                    'plan_name' => $organization->subscription->plan?->name,
                    'trial_ends_at' => DisplayFormat::dateTime($organization->subscription->trial_ends_at),
                    'current_period_end' => DisplayFormat::dateTime($organization->subscription->current_period_end),
                    'past_due_at' => DisplayFormat::dateTime($organization->subscription->past_due_at),
                    'canceled_at' => DisplayFormat::dateTime($organization->subscription->canceled_at),
                    'is_accessible' => $organizationAccessibility->isAccessible($organization),
                    'trial_days_left' => $organization->subscription->daysUntilTrialEnds(),
                    'on_grace_period' => $organization->subscription->onGracePeriod(),
                ]
                : null,
        ]);
    }

    public function updateNotes(
        UpdateOrganizationPlatformNotesRequest $request,
        Organization $organization,
        RecordPlatformAuditLog $recordPlatformAuditLog,
    ): RedirectResponse {
        $organization->update($request->validated());

        $recordPlatformAuditLog->execute(
            action: PlatformAuditLog::ACTION_ORGANIZATION_NOTES_UPDATED,
            platformAdmin: $request->user(),
            subject: $organization,
            request: $request,
        );

        return redirect()
            ->route('platform.organizations.show', $organization)
            ->with('status', 'Notas internas atualizadas.');
    }

    public function suspend(
        SuspendOrganizationRequest $request,
        Organization $organization,
        SuspendOrganization $suspendOrganization,
    ): RedirectResponse {
        abort_if($organization->status === Organization::STATUS_SUSPENDED, 422);

        $suspendOrganization->execute(
            organization: $organization,
            platformAdmin: $request->user(),
            reason: $request->validated('reason'),
            request: $request,
        );

        return redirect()
            ->route('platform.organizations.show', $organization)
            ->with('status', 'Organização suspensa.');
    }

    public function reactivate(
        Request $request,
        Organization $organization,
        ReactivateOrganization $reactivateOrganization,
    ): RedirectResponse {
        abort_unless($request->user()?->isPlatformAdmin(), 403);
        abort_if($organization->status === Organization::STATUS_ACTIVE, 422);

        $reactivateOrganization->execute(
            organization: $organization,
            platformAdmin: $request->user(),
            request: $request,
        );

        return redirect()
            ->route('platform.organizations.show', $organization)
            ->with('status', 'Organização reativada.');
    }

    /**
     * @return Collection<int, array{value: int, label: string}>
     */
    private function activePlanOptions(): Collection
    {
        return Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->map(fn (Plan $plan): array => [
                'value' => $plan->id,
                'label' => $plan->name,
            ]);
    }
}
