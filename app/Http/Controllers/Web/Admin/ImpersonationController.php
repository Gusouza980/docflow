<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Platform\ImpersonateOrganizationOwner;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function store(
        Organization $organization,
        Request $request,
        ImpersonateOrganizationOwner $impersonateOrganizationOwner,
    ): RedirectResponse {
        $impersonateOrganizationOwner->execute(
            platformAdmin: $request->user('admin'),
            organization: $organization,
            request: $request,
        );

        return redirect()
            ->route('dashboard')
            ->with('status', "Você está vendo a plataforma como o dono de {$organization->name}.");
    }
}
