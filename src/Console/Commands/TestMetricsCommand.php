<?php

namespace Faktly\LaravelPrometheusMetrics\Console\Commands;

use Faktly\LaravelPrometheusMetrics\MetricsAggregator;
use Illuminate\Console\Command;

class TestMetricsCommand extends Command
{
    protected $signature = 'prometheus:test-metrics {--format=prometheus : Output format (prometheus|json|yaml)}';

    protected $description = 'Test metrics collection and print in Prometheus, JSON or YAML format';

    public function handle(MetricsAggregator $aggregator): int
    {
        $this->info('Collecting Prometheus metrics...');
        $metrics = $aggregator->collect();
        $format = $this->option('format');

        match ($format) {
            'json' => $this->outputJson($metrics),
            'yaml' => $this->outputYaml($metrics),
            'prometheus' => $this->outputPrometheus($metrics),
            default => $this->error("Unknown format: $format"),
        };

        return self::SUCCESS;
    }

    private function outputJson(array $metrics): void
    {
        $this->line(json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function outputYaml(array $metrics): void
    {
        $this->line(yaml_emit($metrics, YAML_UTF8_ENCODING));
    }

    private function outputPrometheus(array $metrics): void
    {
        $output = '';

        foreach ($metrics as $category => $data) {
            if (empty($data) || !is_array($data)) {
                continue;
            }

            $output .= $this->formatPrometheusMetrics($category, $data);
        }

        $this->line($output);
    }

    private function formatPrometheusMetrics(string $category, array $data): string
    {
        $lines = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $lines[] = $this->formatHistogram($category, $key, $value);
            } elseif (is_scalar($value)) {
                $metricName = 'laravel_' . $category . '_' . $key;
                $lines[] = sprintf('%s %s', $metricName, $value);
            }
        }

        return implode("\n", array_filter($lines)) . "\n";
    }

    private function formatHistogram(string $category, string $key, array $data): string
    {
        $lines = [];
        $safeName = preg_replace('/[^\w:.]/', '_', $key);
        $metricName = 'laravel_' . $category . '_' . $safeName;

        if (isset($data['sum']) && isset($data['total'])) {
            $lines[] = sprintf('%s_sum %s', $metricName, $data['sum']);
            $lines[] = sprintf('%s_count %s', $metricName, $data['total']);
        } elseif (is_array($data) && !empty($data)) {
            $numericData = array_filter($data, 'is_numeric');

            if (!empty($numericData)) {
                $sum = array_sum($numericData);
                $count = count($numericData);
                $lines[] = sprintf('%s_sum %s', $metricName, $sum);
                $lines[] = sprintf('%s_count %s', $metricName, $count);
            }
        }

        return implode("\n", $lines);
    }
}
