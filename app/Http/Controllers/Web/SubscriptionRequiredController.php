<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Billing\OrganizationAccessibility;
use App\Support\WebOrganizationContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionRequiredController extends Controller
{
    public function show(
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        OrganizationAccessibility $organizationAccessibility,
    ): Response {
        $membership = $webOrganizationContext->membership($request);
        $organization = $membership?->organization;
        $blockReason = $organization
            ? $organizationAccessibility->blockReason($organization)
            : null;

        return Inertia::render('Subscription/Required', [
            'organization' => $organization ? [
                'id' => $organization->id,
                'name' => $organization->name,
            ] : null,
            'blockReason' => $blockReason,
            'blockMessage' => $organizationAccessibility->blockMessage($blockReason),
            'canManagePlan' => (bool) $membership?->isAdmin(),
        ]);
    }
}
