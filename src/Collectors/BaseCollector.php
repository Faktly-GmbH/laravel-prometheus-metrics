<?php

namespace Faktly\LaravelPrometheusMetrics\Collectors;

use Faktly\LaravelPrometheusMetrics\Contracts\MetricsCollector;
use Illuminate\Support\Facades\Cache;
use Log;
use Throwable;

abstract class BaseCollector implements MetricsCollector
{
    abstract public function collect(): array;

    public function getCacheKey(): string
    {
        return 'prometheus_metrics:' . $this->getName();
    }

    abstract public function getName(): string;

    protected function handleException(string $context, Throwable $e): void
    {
        Log::warning(
            sprintf(
                '[PrometheusMetrics] %s: %s',
                $context,
                $e->getMessage()
            )
        );
    }

    protected function isEnabled(string $feature): bool
    {
        return (bool)config("prometheus-metrics.collectors.config.{$feature}.enabled", true);
    }

    protected function cacheMetrics(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if (!config('prometheus-metrics.cache.enabled', true)) {
            return $callback();
        }

        $ttl ??= (int)config('prometheus-metrics.cache.ttl', 60);
        $prefix = config('prometheus-metrics.cache.prefix', 'prometheus_metrics:');
        $cacheKey = $prefix . $key;

        return Cache::remember($cacheKey, $ttl, $callback);
    }
}
