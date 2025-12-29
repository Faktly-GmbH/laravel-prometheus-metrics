<?php

namespace Faktly\LaravelPrometheusMetrics\Http\Resource;

use Faktly\LaravelPrometheusMetrics\Support\Output\MetricsResource;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PrometheusMetricsResource extends MetricsResource
{
    public function toResponse(): Response
    {
        $lines = [];

        // Handle HTTP metrics with custom formatting first
        if (isset($this->metrics['http']) && is_array($this->metrics['http'])) {
            $lines = array_merge($lines, $this->formatHttpMetrics($this->metrics['http']));
        }

        // Handle other metrics with label_maps
        $labelMaps = (array)config('prometheus-metrics.prometheus.label_maps', []);

        foreach ($labelMaps as $map) {
            $path = (string)($map['path'] ?? '');

            // Skip http metrics (already handled)
            if (str_starts_with($path, 'http.')) {
                continue;
            }

            $metric = (string)($map['metric'] ?? '');
            $label = (string)($map['label'] ?? '');
            $snake = (bool)($map['snake_case_values'] ?? true);

            if ($path === '' || $metric === '' || $label === '') {
                continue;
            }

            $assoc = $this->getValueByPath($this->metrics, $path);

            if (!is_array($assoc)) {
                continue;
            }

            foreach ($assoc as $k => $v) {
                if (!is_numeric($v)) {
                    continue;
                }

                $labelValue = (string)$k;
                if ($snake) {
                    $labelValue = Str::snake($labelValue);
                }

                $metricName = $this->sanitizeName($metric);
                $labelName = $this->sanitizeLabelName($label);
                $labelValue = $this->escapeLabelValue($labelValue);

                $lines[] = sprintf('%s{%s="%s"} %s', $metricName, $labelName, $labelValue, $v);
            }
        }

        // Emit remaining numeric scalars
        $excludedPaths = array_values(
            array_filter(
                array_map(
                    fn ($m) => (string)($m['path'] ?? ''),
                    $labelMaps
                )
            )
        );

        // Also exclude http metrics from flattening
        $excludedPaths[] = 'http';

        $flat = $this->flatten($this->metrics);

        foreach ($flat as $name => $value) {
            if (!is_numeric($value)) {
                continue;
            }

            if ($this->isExcludedByPath($name, $excludedPaths)) {
                continue;
            }

            $metricName = $this->sanitizeName($name);

            if (str_ends_with($metricName, '_port')) {
                continue;
            }

            $lines[] = sprintf('%s %s', $metricName, $value);
        }

        sort($lines);
        $body = implode("\n", $lines) . "\n";

        return new Response($body, 200, ['Content-Type' => 'text/plain; version=0.0.4']);
    }

    private function formatHttpMetrics(array $httpMetrics): array
    {
        $lines = [];
        $prefix = config('prometheus-metrics.prometheus.prefix', 'laravel_');

        if (isset($httpMetrics['requests_total']) && is_array($httpMetrics['requests_total'])) {
            foreach ($httpMetrics['requests_total'] as $label => $count) {
                if (is_numeric($count)) {
                    $parts = explode(':', $label, 3);
                    if (count($parts) === 3) {
                        $metricName = $prefix . 'http_requests_total';
                        $lines[] = sprintf(
                            '%s{method="%s",path="%s",status="%s"} %d',
                            $metricName,
                            $this->escapeLabelValue($parts[0]),
                            $this->escapeLabelValue($parts[1]),
                            $this->escapeLabelValue($parts[2]),
                            (int)$count
                        );
                    }
                }
            }
        }

        if (isset($httpMetrics['request_duration_seconds']) && is_array($httpMetrics['request_duration_seconds'])) {
            foreach ($httpMetrics['request_duration_seconds'] as $label => $histogram) {
                if (!is_array($histogram)) {
                    continue;
                }

                $parts = explode(':', $label, 3);
                if (count($parts) !== 3) {
                    continue;
                }

                $metricName = $prefix . 'http_request_duration_seconds';
                $method = $this->escapeLabelValue($parts[0]);
                $path = $this->escapeLabelValue($parts[1]);
                $status = $this->escapeLabelValue($parts[2]);

                // Buckets
                foreach ($histogram as $bucket => $count) {
                    if (in_array($bucket, ['sum', 'total'], true)) {
                        continue;
                    }

                    $lines[] = sprintf(
                        '%s_bucket{method="%s",path="%s",status="%s",le="%s"} %d',
                        $metricName,
                        $method,
                        $path,
                        $status,
                        $bucket,
                        (int)$count
                    );
                }

                // +Inf bucket
                $lines[] = sprintf(
                    '%s_bucket{method="%s",path="%s",status="%s",le="+Inf"} %d',
                    $metricName,
                    $method,
                    $path,
                    $status,
                    (int)($histogram['total'] ?? 0)
                );

                // Sum and count
                $lines[] = sprintf(
                    '%s_sum{method="%s",path="%s",status="%s"} %f',
                    $metricName,
                    $method,
                    $path,
                    $status,
                    (float)($histogram['sum'] ?? 0)
                );

                $lines[] = sprintf(
                    '%s_count{method="%s",path="%s",status="%s"} %d',
                    $metricName,
                    $method,
                    $path,
                    $status,
                    (int)($histogram['total'] ?? 0)
                );
            }
        }

        if (isset($httpMetrics['request_size_bytes']) && is_array($httpMetrics['request_size_bytes'])) {
            foreach ($httpMetrics['request_size_bytes'] as $label => $histogram) {
                if (!is_array($histogram)) {
                    continue;
                }

                $parts = explode(':', $label, 2);
                if (count($parts) !== 2) {
                    continue;
                }

                $metricName = $prefix . 'http_request_size_bytes';
                $method = $this->escapeLabelValue($parts[0]);
                $path = $this->escapeLabelValue($parts[1]);

                $buckets = [100, 1000, 10000, 100000, 1000000];

                // Output pre-calculated buckets
                foreach ($buckets as $bucket) {
                    $count = (int)($histogram[(string)$bucket] ?? 0);

                    $lines[] = sprintf(
                        '%s_bucket{method="%s",path="%s",le="%d"} %d',
                        $metricName,
                        $method,
                        $path,
                        $bucket,
                        $count
                    );
                }

                $lines[] = sprintf(
                    '%s_bucket{method="%s",path="%s",le="+Inf"} %d',
                    $metricName,
                    $method,
                    $path,
                    (int)($histogram['total'] ?? 0)
                );

                $lines[] = sprintf(
                    '%s_sum{method="%s",path="%s"} %f',
                    $metricName,
                    $method,
                    $path,
                    (float)($histogram['sum'] ?? 0)
                );

                $lines[] = sprintf(
                    '%s_count{method="%s",path="%s"} %d',
                    $metricName,
                    $method,
                    $path,
                    (int)($histogram['total'] ?? 0)
                );
            }
        }

        if (isset($httpMetrics['response_size_bytes']) && is_array($httpMetrics['response_size_bytes'])) {
            foreach ($httpMetrics['response_size_bytes'] as $label => $histogram) {
                if (!is_array($histogram)) {
                    continue;
                }

                $parts = explode(':', $label, 2);
                if (count($parts) !== 2) {
                    continue;
                }

                $metricName = $prefix . 'http_response_size_bytes';
                $method = $this->escapeLabelValue($parts[0]);
                $path = $this->escapeLabelValue($parts[1]);

                $buckets = [100, 1000, 10000, 100000, 1000000];

                foreach ($buckets as $bucket) {
                    $count = (int)($histogram[(string)$bucket] ?? 0);

                    $lines[] = sprintf(
                        '%s_bucket{method="%s",path="%s",le="%d"} %d',
                        $metricName,
                        $method,
                        $path,
                        $bucket,
                        $count
                    );
                }

                $lines[] = sprintf(
                    '%s_bucket{method="%s",path="%s",le="+Inf"} %d',
                    $metricName,
                    $method,
                    $path,
                    (int)($histogram['total'] ?? 0)
                );

                $lines[] = sprintf(
                    '%s_sum{method="%s",path="%s"} %f',
                    $metricName,
                    $method,
                    $path,
                    (float)($histogram['sum'] ?? 0)
                );

                $lines[] = sprintf(
                    '%s_count{method="%s",path="%s"} %d',
                    $metricName,
                    $method,
                    $path,
                    (int)($histogram['total'] ?? 0)
                );
            }
        }

        return $lines;
    }

    protected function escapeLabelValue(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace("\n", '\\n', $value);

        return str_replace('"', '\"', $value);
    }

    protected function getValueByPath(array $data, string $path): mixed
    {
        $current = $data;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    protected function sanitizeName(string $name): string
    {
        $name = config('prometheus-metrics.prometheus.prefix', 'laravel_') . str_replace('.', '_', $name);

        $name = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $name) ?? $name;
        $name = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $name) ?? $name;

        $name = strtolower($name);

        $name = preg_replace('/[^a-z0-9_]/', '_', $name) ?? $name;
        $name = preg_replace('/_+/', '_', $name) ?? $name;

        return trim($name, '_');
    }

    protected function sanitizeLabelName(string $name): string
    {
        $name = Str::snake($name);
        $name = preg_replace('/[^a-z0-9_]/', '_', $name) ?? $name;
        $name = preg_replace('/_+/', '_', $name) ?? $name;

        return trim($name, '_');
    }

    protected function flatten(array $data, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $fullKey = $prefix === '' ? (string)$key : $prefix . '.' . $key;

            if (is_array($value)) {
                $result += $this->flatten($value, $fullKey);
                continue;
            }

            $result[$fullKey] = $value;
        }

        return $result;
    }

    protected function isExcludedByPath(string $flatKey, array $excludedPaths): bool
    {
        foreach ($excludedPaths as $path) {
            if ($path === '') {
                continue;
            }

            if ($flatKey === $path || str_starts_with($flatKey, $path . '.')) {
                return true;
            }
        }

        return false;
    }
}
