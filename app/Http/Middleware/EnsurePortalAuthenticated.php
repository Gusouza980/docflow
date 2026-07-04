<?php

namespace App\Http\Middleware;

use App\Models\ClientPortalAccess;
use App\Support\Billing\OrganizationAccessibility;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalAuthenticated
{
    public function __construct(private OrganizationAccessibility $organizationAccessibility) {}

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

        return $next($request);
    }
}
