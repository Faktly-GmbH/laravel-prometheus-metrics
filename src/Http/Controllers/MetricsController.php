<?php

namespace Faktly\LaravelPrometheusMetrics\Http\Controllers;

use Faktly\LaravelPrometheusMetrics\Http\Resource\JsonMetricsResource;
use Faktly\LaravelPrometheusMetrics\Http\Resource\PrometheusMetricsResource;
use Faktly\LaravelPrometheusMetrics\Http\Resource\YamlMetricsResource;
use Faktly\LaravelPrometheusMetrics\MetricsAggregator;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MetricsController
{
    public function __invoke(MetricsAggregator $aggregator): JsonResponse|Response
    {
        if (!config('prometheus-metrics.enabled')) {
            return response()->json(['error' => 'Metrics disabled'], \Illuminate\Http\Response::HTTP_I_AM_A_TEAPOT);
        }

        $metrics = $aggregator->collect();
        $format = config('prometheus-metrics.output.format', 'json');

        return match ($format) {
            'yaml' => YamlMetricsResource::make($metrics)->toResponse(),
            'prometheus' => PrometheusMetricsResource::make($metrics)->toResponse(),
            default => JsonMetricsResource::make($metrics)->toResponse(),
        };
    }
}
