<?php

namespace App\Http\Middleware;

use App\Support\Billing\OrganizationAccessibility;
use App\Support\Impersonation;
use App\Support\WebOrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationAccessible
{
    public function __construct(
        private WebOrganizationContext $webOrganizationContext,
        private OrganizationAccessibility $organizationAccessibility,
        private Impersonation $impersonation,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $membership = $this->webOrganizationContext->membership($request);

        if ($membership === null) {
            return redirect()->route('organizations.unassigned');
        }

        $organization = $membership->organization;

        if ($this->impersonation->isActive($request)) {
            abort_unless(
                $this->impersonation->organizationId($request) === $membership->organization_id,
                Response::HTTP_FORBIDDEN,
                'A impersonação está limitada à organização selecionada.',
            );

            return $next($request);
        }

        if ($request->routeIs(
            'subscription.required',
            'organizations.plan.show',
            'organizations.billing.show',
            'organizations.billing.change-plan',
            'organizations.billing.cancel',
        )) {
            return $next($request);
        }

        if ($this->organizationAccessibility->isAccessible($organization)) {
            return $next($request);
        }

        $blockReason = $this->organizationAccessibility->blockReason($organization);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->organizationAccessibility->blockMessage($blockReason),
                'code' => 'subscription_inaccessible',
                'reason' => $blockReason,
            ], Response::HTTP_FORBIDDEN);
        }

        return redirect()
            ->route('subscription.required')
            ->with('error', $this->organizationAccessibility->blockMessage($blockReason));
    }
}
