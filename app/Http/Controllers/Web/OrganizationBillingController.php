<?php

namespace App\Http\Controllers\Web;

use App\Actions\Billing\CancelSubscription;
use App\Actions\Billing\RequestPlanChange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CancelOrganizationBillingRequest;
use App\Http\Requests\Web\ChangeOrganizationBillingPlanRequest;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Support\Billing\PlanLimitChecker;
use App\Support\DisplayFormat;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class OrganizationBillingController extends Controller
{
    public function show(
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        PlanLimitChecker $planLimitChecker,
    ): Response|RedirectResponse {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização.');
        }

        abort_unless($membership->isAdmin(), HttpResponse::HTTP_FORBIDDEN);

        $organization = $membership->organization->load(['subscription.plan']);
        $subscription = $organization->subscription;

        $invoices = SubscriptionInvoice::query()
            ->where('organization_id', $organization->id)
            ->latest('id')
            ->limit(24)
            ->get()
            ->map(fn (SubscriptionInvoice $invoice): array => [
                'id' => $invoice->id,
                'amount_cents' => $invoice->amount_cents,
                'currency' => $invoice->currency,
                'status' => $invoice->status,
                'due_at' => DisplayFormat::dateTime($invoice->due_at),
                'paid_at' => DisplayFormat::dateTime($invoice->paid_at),
                'period_start' => DisplayFormat::dateTime($invoice->period_start),
                'period_end' => DisplayFormat::dateTime($invoice->period_end),
            ]);

        $nextOpenInvoice = SubscriptionInvoice::query()
            ->where('organization_id', $organization->id)
            ->where('status', SubscriptionInvoice::STATUS_OPEN)
            ->orderBy('due_at')
            ->first();

        $lastPaidInvoice = SubscriptionInvoice::query()
            ->where('organization_id', $organization->id)
            ->where('status', SubscriptionInvoice::STATUS_PAID)
            ->latest('paid_at')
            ->first();

        $publicPlans = Plan::query()
            ->where('is_public', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'description', 'price_cents', 'billing_interval', 'limits', 'features'])
            ->map(fn (Plan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price_cents' => $plan->price_cents,
                'billing_interval' => $plan->billing_interval,
                'limits' => $plan->limits ?? [],
                'features' => $plan->features ?? [],
            ]);

        return Inertia::render('Organizations/Billing', [
            'summary' => $planLimitChecker->usageSummary($organization),
            'subscription' => $subscription ? [
                'status' => $subscription->status,
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
                'current_period_end' => DisplayFormat::dateTime($subscription->current_period_end),
                'pending_plan_id' => $subscription->metadata['pending_plan_id'] ?? null,
            ] : null,
            'invoices' => $invoices,
            'nextOpenInvoice' => $nextOpenInvoice ? [
                'id' => $nextOpenInvoice->id,
                'amount_cents' => $nextOpenInvoice->amount_cents,
                'due_at' => DisplayFormat::dateTime($nextOpenInvoice->due_at),
            ] : null,
            'lastPaidInvoice' => $lastPaidInvoice ? [
                'id' => $lastPaidInvoice->id,
                'amount_cents' => $lastPaidInvoice->amount_cents,
                'paid_at' => DisplayFormat::dateTime($lastPaidInvoice->paid_at),
            ] : null,
            'publicPlans' => $publicPlans,
        ]);
    }

    public function changePlan(
        ChangeOrganizationBillingPlanRequest $request,
        WebOrganizationContext $webOrganizationContext,
        RequestPlanChange $requestPlanChange,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership?->isAdmin(), HttpResponse::HTTP_FORBIDDEN);

        $plan = Plan::query()->findOrFail($request->validated('plan_id'));
        $requestPlanChange->execute($membership->organization, $plan);

        return redirect()
            ->route('organizations.billing.show')
            ->with('status', 'Solicitação de alteração de plano registrada.');
    }

    public function cancel(
        CancelOrganizationBillingRequest $request,
        WebOrganizationContext $webOrganizationContext,
        CancelSubscription $cancelSubscription,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership?->isAdmin(), HttpResponse::HTTP_FORBIDDEN);

        $cancelSubscription->execute($membership->organization, immediate: false);

        return redirect()
            ->route('organizations.billing.show')
            ->with('status', 'Cancelamento agendado para o fim do período atual.');
    }
}
