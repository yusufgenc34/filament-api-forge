<?php

namespace YusufGenc34\FilamentApiForge\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    /**
     * Force the request to be treated as JSON to prevent Laravel
     * from redirecting to a 'login' route on authentication failure.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
