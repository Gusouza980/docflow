<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\BuildsMyDayPayload;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyDayController extends Controller
{
    public function __invoke(
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        BuildsMyDayPayload $buildsMyDayPayload,
    ): Response|RedirectResponse {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização.');
        }

        return Inertia::render('MyDay/Index', $buildsMyDayPayload->for($membership));
    }
}
