<?php

namespace App\Http\Controllers\Web\Platform;

use App\Actions\Billing\ChangeOrganizationPlan;
use App\Actions\Platform\RecordPlatformAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Platform\StoreOrganizationPlanOverrideRequest;
use App\Http\Requests\Web\Platform\UpdateOrganizationPlanRequest;
use App\Models\Organization;
use App\Models\OrganizationPlanOverride;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationPlanController extends Controller
{
    public function updatePlan(
        UpdateOrganizationPlanRequest $request,
        Organization $organization,
        ChangeOrganizationPlan $changeOrganizationPlan,
        RecordPlatformAuditLog $recordPlatformAuditLog,
    ): RedirectResponse {
        $plan = Plan::query()->findOrFail($request->validated('plan_id'));
        $subscription = $changeOrganizationPlan->execute($organization, $plan);

        $recordPlatformAuditLog->execute(
            action: 'platform.organization.plan_updated',
            platformAdmin: $request->user(),
            subject: $organization,
            metadata: ['plan_id' => $plan->id, 'subscription_id' => $subscription->id],
            request: $request,
        );

        return redirect()
            ->route('platform.organizations.show', $organization)
            ->with('status', 'Plano da organização atualizado.');
    }

    public function storeOverride(
        StoreOrganizationPlanOverrideRequest $request,
        Organization $organization,
        RecordPlatformAuditLog $recordPlatformAuditLog,
    ): RedirectResponse {
        $override = OrganizationPlanOverride::create([
            ...$request->validated(),
            'organization_id' => $organization->id,
            'created_by_user_id' => $request->user()->id,
        ]);

        $recordPlatformAuditLog->execute(
            action: 'platform.organization.plan_override_created',
            platformAdmin: $request->user(),
            subject: $organization,
            metadata: ['override_id' => $override->id],
            request: $request,
        );

        return redirect()
            ->route('platform.organizations.show', $organization)
            ->with('status', 'Override de plano registrado.');
    }

    public function destroyOverride(
        Request $request,
        Organization $organization,
        OrganizationPlanOverride $override,
        RecordPlatformAuditLog $recordPlatformAuditLog,
    ): RedirectResponse {
        abort_if($override->organization_id !== $organization->id, 404);

        $override->delete();

        $recordPlatformAuditLog->execute(
            action: 'platform.organization.plan_override_removed',
            platformAdmin: $request->user(),
            subject: $organization,
            metadata: ['override_id' => $override->id],
            request: $request,
        );

        return redirect()
            ->route('platform.organizations.show', $organization)
            ->with('status', 'Override removido.');
    }
}
