<?php

namespace YusufGenc34\FilamentApiForge\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetApiForgeVersion
{
    public function handle(Request $request, Closure $next, string $version): mixed
    {
        $request->attributes->set('api_forge_version', $version);

        return $next($request);
    }
}
