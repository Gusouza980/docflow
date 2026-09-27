<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectPlatformAdminFromTenantApi
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isPlatformAdmin()) {
            return response()->json([
                'message' => 'Platform administrators cannot use the tenant API.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
