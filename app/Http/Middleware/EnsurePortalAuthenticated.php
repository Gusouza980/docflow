<?php

namespace App\Http\Middleware;

use App\Models\ClientPortalAccess;
use App\Models\Plan;
use App\Support\Billing\OrganizationAccessibility;
use App\Support\Billing\PlanLimitChecker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalAuthenticated
{
    public function __construct(
        private OrganizationAccessibility $organizationAccessibility,
        private PlanLimitChecker $planLimitChecker,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('portal')->check()) {
            return redirect()->route('portal.login');
        }

        $access = auth('portal')->user();

        if ($access->status !== ClientPortalAccess::STATUS_ACTIVE) {
            auth('portal')->logout();

            return redirect()->route('portal.login')->with('error', 'Seu acesso ao portal foi revogado.');
        }

        if (! $access->hasCompletedOnboarding()) {
            return redirect()->route('client-portal.onboarding');
        }

        $access->loadMissing(['organization.subscription']);

        if (! $this->organizationAccessibility->isAccessible($access->organization)) {
            auth('portal')->logout();

            return redirect()
                ->route('portal.login')
                ->with('error', $this->organizationAccessibility->blockMessage(
                    $this->organizationAccessibility->blockReason($access->organization)
                ));
        }

        if (Plan::query()->exists() && ! $this->planLimitChecker->hasFeature($access->organization, 'portal')) {
            auth('portal')->logout();

            return redirect()
                ->route('portal.login')
                ->with('error', 'O portal do cliente não está incluído no plano deste escritório.');
        }

        return $next($request);
    }
}
