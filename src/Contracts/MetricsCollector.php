<?php

namespace Faktly\LaravelPrometheusMetrics\Contracts;

interface MetricsCollector
{
    /**
     * Unique key used in the metrics payload, e.g. "your_collector". Which
     * could result in "prometheus_metrics_your_collector_your_metric1" key
     */
    public function getName(): string;

    /**
     * Collect metrics. Must always return an array, never throw. Should return
     * keys like your_metric1 which combined with getName results in
     * "prometheus_metrics_your_collector_your_metric1".
     *
     * @return array<string,mixed>
     */
    public function collect(): array;

    /**
     * Cache key for this collector, if your system uses caching.
     * E.g. prometheus_metrics:your_collector
     */
    public function getCacheKey(): string;
}
