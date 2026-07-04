<?php

namespace App\Http\Controllers\Web\Platform;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $totalOrganizations = Organization::query()->count();
        $activeOrganizations = Organization::query()
            ->where('status', Organization::STATUS_ACTIVE)
            ->count();
        $suspendedOrganizations = Organization::query()
            ->where('status', Organization::STATUS_SUSPENDED)
            ->count();

        $mrrCents = Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->sum('plans.price_cents');

        $pastDueOrganizations = Subscription::query()
            ->where('status', Subscription::STATUS_PAST_DUE)
            ->count();

        $trialsExpiringSoon = Subscription::query()
            ->where('status', Subscription::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(7)])
            ->count();

        $overdueInvoices = SubscriptionInvoice::query()
            ->where('status', SubscriptionInvoice::STATUS_OPEN)
            ->where('due_at', '<', now())
            ->count();

        return Inertia::render('Platform/Dashboard/Index', [
            'metrics' => [
                'total_organizations' => $totalOrganizations,
                'active_organizations' => $activeOrganizations,
                'suspended_organizations' => $suspendedOrganizations,
                'mrr_cents' => (int) $mrrCents,
                'past_due_organizations' => $pastDueOrganizations,
                'trials_expiring_soon' => $trialsExpiringSoon,
                'overdue_invoices' => $overdueInvoices,
            ],
        ]);
    }
}
