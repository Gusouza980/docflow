<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DenyWhenImpersonating
{
    public function __construct(private Impersonation $impersonation) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            $this->impersonation->isActive($request),
            Response::HTTP_FORBIDDEN,
            'Esta ação não está disponível durante a impersonação.',
        );

        return $next($request);
    }
}
