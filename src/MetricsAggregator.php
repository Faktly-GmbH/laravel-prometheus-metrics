<?php

namespace Faktly\LaravelPrometheusMetrics;

use Faktly\LaravelPrometheusMetrics\Contracts\MetricsCollector;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;

class MetricsAggregator
{
    /**
     * @var MetricsCollector[]
     */
    protected array $collectors = [];

    public function __construct(Container $container)
    {
        $collectorClasses = config('prometheus-metrics.collectors.classes', []);

        foreach ($collectorClasses as $collectorClass) {
            /** @var MetricsCollector $collector */
            $collector = $container->make($collectorClass);

            // Check the per-collector enabled flag, if available
            $name = $collector->getName();
            $enabled = Arr::get(
                config('prometheus-metrics.collectors.config'),
                "{$name}.enabled",
                true
            );

            if (!$enabled) {
                continue;
            }

            $this->collectors[$name] = $collector;
        }
    }

    public function collect(): array
    {
        $result = [
            'timestamp' => now()->toDateTimeString(),
            'metadata'  => [
                'app_name'    => config('prometheus-metrics.metadata.app_name', 'Laravel Prometheus Metrics'),
                'app_env'     => config('prometheus-metrics.metadata.environment', 'testing'),
                'app_version' => config('prometheus-metrics.metadata.app_version', '0.0.1'),
            ],
        ];

        foreach ($this->collectors as $name => $collector) {
            $result[$name] = $collector->collect();
        }

        return $result;
    }
}
