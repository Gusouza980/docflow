<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\BuildsDashboardPayload;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        BuildsDashboardPayload $dashboardPayload,
    ): Response|RedirectResponse {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.unassigned');
        }

        $payload = $dashboardPayload->fromRequest($request, $membership);

        return Inertia::render('Dashboard/Index', $payload);
    }
}
