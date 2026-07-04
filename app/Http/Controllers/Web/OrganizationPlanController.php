<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Billing\PlanLimitChecker;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class OrganizationPlanController extends Controller
{
    public function show(Request $request, WebOrganizationContext $webOrganizationContext, PlanLimitChecker $planLimitChecker): Response|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização para consultar o plano.');
        }

        abort_unless($membership->isAdmin(), HttpResponse::HTTP_FORBIDDEN);

        $organization = $membership->organization->load('plan');

        return Inertia::render('Organizations/Plan', [
            'summary' => $planLimitChecker->usageSummary($organization),
        ]);
    }
}
