<?php

namespace Faktly\LaravelPrometheusMetrics\Http\Resource;

use Faktly\LaravelPrometheusMetrics\Support\Output\MetricsResource;
use Illuminate\Http\JsonResponse;

class JsonMetricsResource extends MetricsResource
{
    public function toResponse(): JsonResponse
    {
        return new JsonResponse(
            $this->metrics,
            200,
            ['Content-Type' => 'application/json']
        );
    }
}
