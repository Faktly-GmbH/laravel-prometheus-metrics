<?php

namespace Faktly\LaravelPrometheusMetrics\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $config = config('prometheus-metrics');

        if (!($config['auth']['enabled'] ?? false)) {
            return $next($request);
        }

        $expectedToken = $config['auth']['token'] ?? null;
        $token = $request->header('X-Metrics-Token');

        if (!$expectedToken) {
            return response()->json(['error' => 'Authentication not configured'], 500);
        }

        if (!hash_equals($expectedToken, (string)$token)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
