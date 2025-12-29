<?php

namespace Faktly\LaravelPrometheusMetrics\Metrics\Request;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

class CacheMetricsStore
{
    private string $prefix = 'prometheus_metrics:http:';

    public function __construct(private CacheRepository $cache)
    {
    }

    public function incrementRequestCount(string $method, string $route, int $statusCode): void
    {
        try {
            $key = $this->prefix . "requests:total:{$method}:{$route}:{$statusCode}";
            $current = (int)$this->cache->get($key, 0);
            $this->cache->put($key, $current + 1, 3600);
            $this->trackKey($key);
        } catch (Throwable $e) {
            // Silently fail
        }
    }

    private function trackKey(string $key): void
    {
        $registryKey = $this->prefix . 'registry:keys';
        $trackedKeys = $this->cache->get($registryKey, []);

        if (!is_array($trackedKeys)) {
            $trackedKeys = [];
        }

        if (!in_array($key, $trackedKeys, true)) {
            $trackedKeys[] = $key;
            $this->cache->put($registryKey, $trackedKeys, 3600);
        }
    }

    public function recordRequestDuration(string $method, string $route, int $statusCode, float $durationMs): void
    {
        try {
            $key = $this->prefix . "duration:{$method}:{$route}:{$statusCode}";
            $seconds = $durationMs / 1000;

            $values = $this->cache->get($key, []);
            if (!is_array($values)) {
                $values = [];
            }

            $values[] = $seconds;

            if (count($values) > 1000) {
                $values = array_slice($values, -1000);
            }

            $this->cache->put($key, $values, 3600);
            $this->trackKey($key);
        } catch (Throwable $e) {
            // Silently fail
        }
    }

    public function recordRequestSize(string $method, string $route, int $contentLengthBytes): void
    {
        try {
            $key = $this->prefix . "request_size_bytes:{$method}:{$route}";
            $values = $this->cache->get($key, []);

            if (!is_array($values)) {
                $values = [];
            }

            $values[] = $contentLengthBytes;

            if (count($values) > 1000) {
                $values = array_slice($values, -1000);
            }

            $this->cache->put($key, $values, 3600);
            $this->trackKey($key);
        } catch (Throwable $e) {
            // Silently fail
        }
    }

    public function recordResponseSize(string $method, string $route, int $contentLengthBytes): void
    {
        try {
            $key = $this->prefix . "response_size_bytes:{$method}:{$route}";
            $values = $this->cache->get($key, []);

            if (!is_array($values)) {
                $values = [];
            }

            $values[] = $contentLengthBytes;

            if (count($values) > 1000) {
                $values = array_slice($values, -1000);
            }

            $this->cache->put($key, $values, 3600);
            $this->trackKey($key);
        } catch (Throwable $e) {
            // Silently fail
        }
    }

    public function getMetrics(): array
    {
        try {
            $metrics = [
                'requests_total'   => [],
                'request_duration' => [],
                'request_size_bytes'     => [],
                'response_size_bytes'    => [],
            ];

            $registryKey = $this->prefix . 'registry:keys';
            $trackedKeys = $this->cache->get($registryKey, []);

            if (!is_array($trackedKeys)) {
                $trackedKeys = [];
            }

            foreach ($trackedKeys as $cacheKey) {
                $value = $this->cache->get($cacheKey);
                $shortKey = str_replace($this->prefix, '', $cacheKey);

                if (str_starts_with($shortKey, 'requests:total:')) {
                    $parts = explode(':', $shortKey, 4);
                    if (count($parts) === 4) {
                        $label = "{$parts[2]}:{$parts[3]}";
                        $metrics['requests_total'][$label] = $value;
                    }
                } elseif (str_starts_with($shortKey, 'duration:')) {
                    $parts = explode(':', $shortKey, 3);
                    if (count($parts) === 3) {
                        $label = "{$parts[1]}:{$parts[2]}";
                        $metrics['request_duration'][$label] = $value ?? [];
                    }
                } elseif (str_starts_with($shortKey, 'request_size_bytes:')) {
                    $label = str_replace('request_size_bytes:', '', $shortKey);
                    $metrics['request_size_bytes'][$label] = $value ?? [];
                } elseif (str_starts_with($shortKey, 'response_size_bytes:')) {
                    $label = str_replace('response_size_bytes:', '', $shortKey);
                    $metrics['response_size_bytes'][$label] = $value ?? [];
                }
            }

            return $metrics;
        } catch (Throwable $e) {
            return [
                'requests_total'   => [],
                'request_duration' => [],
                'request_size_bytes'     => [],
                'response_size_bytes'    => [],
            ];
        }
    }

    public function flush(): void
    {
        try {
            $registryKey = $this->prefix . 'registry:keys';
            $trackedKeys = $this->cache->get($registryKey, []);

            if (is_array($trackedKeys)) {
                foreach ($trackedKeys as $key) {
                    $this->cache->forget($key);
                }
            }

            $this->cache->forget($registryKey);
        } catch (Throwable $e) {
            // Silently fail
        }
    }
}
