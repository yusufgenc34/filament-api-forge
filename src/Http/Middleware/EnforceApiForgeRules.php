<?php

namespace YusufGenc34\FilamentApiForge\Http\Middleware;

use Closure;
use YusufGenc34\FilamentApiForge\Contracts\HasApi;
use YusufGenc34\FilamentApiForge\Models\ApiForgeResourceSetting;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class EnforceApiForgeRules
{
    public function __construct(
        protected ResourceDiscoveryService $discoveryService,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $panelId  = $request->route('panelId');
        $slug     = $request->route('resourceSlug');

        if (! $panelId || ! $slug) {
            return $next($request);
        }

        $resourceData = $this->discoveryService->findResource($panelId, $slug);

        if (! $resourceData) {
            return $next($request);
        }

        $resourceClass = $resourceData['resource_class'];
        $action        = $this->resolveAction($request);
        $setting       = ApiForgeResourceSetting::where('resource_class', $resourceClass)->first();

        // ── IP check ─────────────────────────────────────────────────────
        $clientIp = $request->ip();

        $methodIps   = $setting ? ($setting->getMethodConfig($action)['allowed_ips'] ?? []) : [];
        $resourceIps = $setting ? ($setting->allowed_ips ?? []) : [];

        // Method-level IPs take precedence; fall back to resource-level
        $effectiveIps = ! empty($methodIps) ? $methodIps : $resourceIps;

        if (! empty($effectiveIps) && ! $this->isIpAllowed($clientIp, $effectiveIps)) {
            return response()->json([
                'message' => 'Your IP address is not allowed to access this resource.',
                'error'   => 'ip_forbidden',
            ], 403);
        }

        // ── Rate limit ───────────────────────────────────────────────────
        $methodLimit   = $setting ? ($setting->getMethodConfig($action)['rate_limit'] ?? null) : null;
        $resourceLimit = $setting?->rate_limit;
        $globalLimit   = (int) config('filament-api-forge.rate_limit', 60);

        $limit = $methodLimit ?? $resourceLimit ?? $globalLimit;

        $key = implode('|', [
            'api-forge-dynamic',
            $resourceClass,
            $action,
            $request->user()?->id ?: $clientIp,
        ]);

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'message'     => 'Too many requests.',
                'error'       => 'rate_limit_exceeded',
                'retry_after' => $retryAfter,
            ], 429)->header('Retry-After', $retryAfter)
                   ->header('X-RateLimit-Limit', $limit)
                   ->header('X-RateLimit-Remaining', 0);
        }

        RateLimiter::hit($key, 60);

        $startedAt = microtime(true);

        $response = $next($request);

        $this->recordAuditLog($request, $resourceClass, $action, $response, $startedAt);

        $remaining = RateLimiter::remaining($key, $limit);

        return $response
            ->header('X-RateLimit-Limit', $limit)
            ->header('X-RateLimit-Remaining', max(0, $remaining));
    }

    private function recordAuditLog(Request $request, string $resourceClass, string $action, mixed $response, float $startedAt): void
    {
        if (! config('filament-api-forge.audit.enabled', true)) {
            return;
        }

        try {
            $token = $request->attributes->get('api_forge_token');

            \YusufGenc34\FilamentApiForge\Models\ApiForgeRequestLog::create([
                'token_id'       => $token?->id,
                'resource_class' => $resourceClass,
                'action'         => substr($action, 0, 64),
                'method'         => substr($request->method(), 0, 10),
                'path'           => substr($request->path(), 0, 2048),
                'status'         => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 0,
                'duration_ms'    => (int) round((microtime(true) - $startedAt) * 1000),
                'ip'             => $request->ip(),
                'created_at'     => now(),
            ]);
        } catch (\Throwable) {
            // Audit logging must never break the API response
        }
    }

    private function resolveAction(Request $request): string
    {
        // Custom action detection
        $actionName = $request->route('actionName');
        if ($actionName) {
            return 'action.' . $actionName;
        }

        // Literal-suffix routes (restore / force delete / export)
        $routeName = $request->route()?->getName();

        $named = match ($routeName) {
            'api-forge.restore'       => 'restore',
            'api-forge.force-destroy' => 'forceDelete',
            'api-forge.export'        => 'export',
            'api-forge.batch'         => 'batch',
            default                   => null,
        };

        if ($named) {
            return $named;
        }

        $method = strtoupper($request->method());

        return match (true) {
            $method === 'GET'    && ! $request->route('recordId') => 'index',
            $method === 'GET'    && (bool) $request->route('recordId') => 'show',
            $method === 'POST'   => 'store',
            in_array($method, ['PUT', 'PATCH']) => 'update',
            $method === 'DELETE' => 'destroy',
            default              => 'index',
        };
    }

    private function isIpAllowed(string $clientIp, array $allowedIps): bool
    {
        foreach ($allowedIps as $rule) {
            $rule = trim($rule);

            if ($rule === '') {
                continue;
            }

            // CIDR range check
            if (str_contains($rule, '/')) {
                if ($this->ipInCidr($clientIp, $rule)) {
                    return true;
                }
                continue;
            }

            // Wildcard (e.g. 192.168.1.*)
            if (str_contains($rule, '*')) {
                $pattern = '/^' . str_replace('\*', '\d+', preg_quote($rule, '/')) . '$/';
                if (preg_match($pattern, $clientIp)) {
                    return true;
                }
                continue;
            }

            if ($clientIp === $rule) {
                return true;
            }
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;

        $ipBin     = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        // Mixed families (IPv4 rule vs IPv6 client or vice versa) never match
        if (strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $maxBits = strlen($ipBin) * 8; // 32 for IPv4, 128 for IPv6

        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        if ($bits === 0) {
            return true;
        }

        $fullBytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainder) & 0xFF;

        return (ord($ipBin[$fullBytes]) & $mask) === (ord($subnetBin[$fullBytes]) & $mask);
    }
}
