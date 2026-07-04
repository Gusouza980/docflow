<?php

namespace App\Http\Controllers\Web\Platform;

use App\Actions\Billing\ActivateSubscription;
use App\Actions\Billing\CancelSubscription;
use App\Actions\Billing\ChangeOrganizationPlan;
use App\Actions\Billing\ExtendTrial;
use App\Actions\Billing\MarkSubscriptionPastDue;
use App\Actions\Billing\PauseSubscription;
use App\Actions\Platform\RecordPlatformAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Platform\ChangeSubscriptionPlanRequest;
use App\Http\Requests\Web\Platform\ExtendTrialRequest;
use App\Models\Organization;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function changePlan(
        ChangeSubscriptionPlanRequest $request,
        Organization $organization,
        ChangeOrganizationPlan $changeOrganizationPlan,
        RecordPlatformAuditLog $recordPlatformAuditLog,
    ): RedirectResponse {
        $plan = Plan::query()->findOrFail($request->validated('plan_id'));
        $subscription = $changeOrganizationPlan->execute($organization, $plan);

        $recordPlatformAuditLog->execute(
            action: 'platform.subscription.plan_changed',
            platformAdmin: $request->user(),
            subject: $organization,
            metadata: ['plan_id' => $plan->id, 'subscription_id' => $subscription->id],
            request: $request,
        );

        return redirect()
            ->route('platform.organizations.show', $organization)
            ->with('status', 'Plano da assinatura atualizado.');
    }

    public function extendTrial(
        ExtendTrialRequest $request,
        Organization $organization,
        ExtendTrial $extendTrial,
        RecordPlatformAuditLog $recordPlatformAuditLog,
    ): RedirectResponse {
        $days = (int) $request->validated('days');
        $subscription = $extendTrial->execute($organization, $days);

        $recordPlatformAuditLog->execute(
            action: 'platform.subscription.trial_extended',
            platformAdmin: $request->user(),
            subject: $organization,
            metadata: ['days' => $days, 'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String()],
            request: $request,
        );

        return redirect()
            ->route('platform.organizations.show', $organization)
            ->with('status', "Trial estendido em {$days} dias.");
    }

    public function cancel(
        Request $request,
        Organization $organization,
        CancelSubscription $cancelSubscription,
        RecordPlatformAuditLog $recordPlatformAuditLog,
    ): RedirectResponse {
        $subscription = $cancelSubscription->execute($organization, immediate: true);

        $recordPlatformAuditLog->execute(
            action: 'platform.subscription.canceled',
            platformAdmin: $request->user(),
            subject: $organization,
            metadata: ['subscription_id' => $subscription->id],
            request: $request,
        );

        return redirect()
            ->route('platform.organizations.show', $organization)
            ->with('status', 'Assinatura cancelada e organização suspensa.');
    }

    public function activate(
        Request $request,
        Organization $organization,
        ActivateSubscription $activateSubscription,
        RecordPlatformAuditLog $recordPlatformAuditLog,
    ): RedirectResponse {
        $subscription = $activateSubscription->execute($organization);

        $recordPlatformAuditLog->execute(
            action: 'platform.subscription.activated',
            platformAdmin: $request->user(),
            subject: $organization,
            metadata: ['subscription_id' => $subscription->id],
            request: $request,
        );

        return redirect()
            ->route('platform.organizations.show', $organization)
            ->with('status', 'Assinatura reativada.');
    }

    public function pause(
        Request $request,
        Organization $organization,
        PauseSubscription $pauseSubscription,
        RecordPlatformAuditLog $recordPlatformAuditLog,
    ): RedirectResponse {
        $subscription = $pauseSubscription->execute($organization);

        $recordPlatformAuditLog->execute(
            action: 'platform.subscription.paused',
            platformAdmin: $request->user(),
            subject: $organization,
            metadata: ['subscription_id' => $subscription->id],
            request: $request,
        );

        return redirect()
            ->route('platform.organizations.show', $organization)
            ->with('status', 'Assinatura pausada.');
    }

    public function markPastDue(
        Request $request,
        Organization $organization,
        MarkSubscriptionPastDue $markSubscriptionPastDue,
        RecordPlatformAuditLog $recordPlatformAuditLog,
    ): RedirectResponse {
        $subscription = $markSubscriptionPastDue->execute($organization);

        $recordPlatformAuditLog->execute(
            action: 'platform.subscription.marked_past_due',
            platformAdmin: $request->user(),
            subject: $organization,
            metadata: ['subscription_id' => $subscription->id],
            request: $request,
        );

        return redirect()
            ->route('platform.organizations.show', $organization)
            ->with('status', 'Assinatura marcada como inadimplente.');
    }
}
