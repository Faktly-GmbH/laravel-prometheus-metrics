<?php

namespace Faktly\LaravelPrometheusMetrics\Collectors;

use Faktly\LaravelPrometheusMetrics\Metrics\Request\CacheMetricsStore;
use stdClass;
use Throwable;

class HTTPCollector extends BaseCollector
{
    private CacheMetricsStore $store;

    public function __construct(CacheMetricsStore $store)
    {
        $this->store = $store;
    }

    public function collect(): array
    {
        if (!$this->isEnabled('http')) {
            return [];
        }

        try {
            $metrics = $this->store->getMetrics();

            return [
                'requests_total'           => $this->formatRequestsTotal($metrics['requests_total'] ?? []),
                'request_duration_seconds' => $this->formatHistogram(
                    $metrics['request_duration'] ?? [],
                    [0.005, 0.01, 0.025, 0.05, 0.075, 0.1, 0.25, 0.5, 0.75, 1, 2.5, 5, 7.5, 10]
                ),
                'request_size_bytes'       => $this->formatHistogram(
                    $metrics['request_size_bytes'] ?? [],
                    [100, 1000, 10000, 100000, 1000000]
                ),
                'response_size_bytes'      => $this->formatHistogram(
                    $metrics['response_size_bytes'] ?? [],
                    [100, 1000, 10000, 100000, 1000000]
                ),
            ];
        } catch (Throwable $e) {
            $this->handleException('HTTPCollector', $e);

            return [
                'requests_total'           => [],
                'request_duration_seconds' => [],
                'request_size_bytes'       => [],
                'response_size_bytes'      => [],
                'error'                    => $e->getMessage(),
            ];
        }
    }

    protected function isEnabled(string $feature): bool
    {
        return (bool)config('prometheus-metrics.collectors.config.http.enabled', true);
    }

    private function formatRequestsTotal(array $counts): array|stdClass
    {
        return count($counts) > 0 ? $counts : [];
    }

    private function formatHistogram(array $histograms, ?array $buckets = null): array
    {
        if (empty($histograms)) {
            return [];
        }

        // Default buckets for duration (in seconds)
        if ($buckets === null) {
            $buckets = [0.005, 0.01, 0.025, 0.05, 0.075, 0.1, 0.25, 0.5, 0.75, 1, 2.5, 5, 7.5, 10];
        }

        $result = [];

        foreach ($histograms as $label => $values) {
            $result[$label] = $this->buildHistogramBuckets($values, $buckets);
        }

        return $result;
    }

    private function buildHistogramBuckets(array $values, ?array $buckets = null): array
    {
        if (empty($values)) {
            return [];
        }

        if ($buckets === null) {
            $buckets = [0.005, 0.01, 0.025, 0.05, 0.075, 0.1, 0.25, 0.5, 0.75, 1, 2.5, 5, 7.5, 10];
        }

        $histogram = [];

        foreach ($buckets as $bucket) {
            $histogram[(string)$bucket] = count(array_filter($values, fn ($v) => $v <= $bucket));
        }

        $histogram['total'] = count($values);
        $histogram['sum'] = (float)array_sum($values);

        return $histogram;
    }

    public function getName(): string
    {
        return 'http';
    }
}
