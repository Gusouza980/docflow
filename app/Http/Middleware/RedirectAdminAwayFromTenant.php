<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectAdminAwayFromTenant
{
    public function __construct(private Impersonation $impersonation) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->impersonation->isActive($request)) {
            Auth::shouldUse('web');

            return $next($request);
        }

        $webUser = $request->user('web');

        if ($webUser && ! $webUser->isPlatformAdmin()) {
            Auth::shouldUse('web');

            return $next($request);
        }

        if (auth('admin')->user()?->isPlatformAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($webUser?->isPlatformAdmin()) {
            Auth::guard('web')->logout();

            return redirect()->route('admin.login');
        }

        Auth::shouldUse('web');

        return $next($request);
    }
}
