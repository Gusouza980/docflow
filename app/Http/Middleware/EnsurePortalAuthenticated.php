<?php

namespace App\Http\Middleware;

use App\Models\ClientPortalAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalAuthenticated
{
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

        return $next($request);
    }
}
