<?php

namespace Faktly\LaravelPrometheusMetrics\Http\Middleware;

use Closure;
use Faktly\LaravelPrometheusMetrics\Metrics\Request\CacheMetricsStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class RecordHttpMetricsMiddleware
{
    private CacheMetricsStore $store;

    public function __construct(CacheMetricsStore $store)
    {
        $this->store = $store;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->isEnabled()) {
            return $next($request);
        }

        $startTime = microtime(true);
        $method = $request->getMethod();
        $routeName = $this->getRouteName($request);
        $requestSize = strlen($request->getContent());

        if ($requestSize > 0) {
            $this->store->recordRequestSize($method, $routeName, $requestSize);
        }

        /** @var Response $response */
        $response = $next($request);
        $durationMs = (microtime(true) - $startTime) * 1000;

        $this->store->incrementRequestCount($method, $routeName, $response->getStatusCode());
        $this->store->recordRequestDuration(
            $method,
            $routeName,
            $response->getStatusCode(),
            $durationMs
        );

        $responseContent = $response->getContent();
        $responseSize = strlen($responseContent);

        if ($responseSize > 0) {
            $this->store->recordResponseSize($method, $routeName, $responseSize);
        }

        $response->headers->set('Content-Length', $responseSize);

        return $response;
    }

    private function isEnabled(): bool
    {
        return (bool)Config::get('prometheus-metrics.collectors.config.http.enabled', true);
    }

    private function getRouteName(Request $request): string
    {
        $path = $request->getPathInfo();

        return $path ?: '/';
    }
}
