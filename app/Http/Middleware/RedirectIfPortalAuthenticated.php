<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfPortalAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth('portal')->check()) {
            return redirect()->route('client-portal.dashboard');
        }

        return $next($request);
    }
}
