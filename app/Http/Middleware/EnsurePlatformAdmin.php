<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform administration uses users.is_platform_admin — not Spatie super-admin.
 *
 * @see database/seeders/PermissionSeeder.php Spatie super-admin is tenant-scoped hook only.
 */
class EnsurePlatformAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('admin');

        $user = $request->user('admin');

        abort_unless($user && $user->isPlatformAdmin(), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
