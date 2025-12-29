<?php

namespace Faktly\LaravelPrometheusMetrics\Support\Output;

use Symfony\Component\HttpFoundation\Response;

abstract class MetricsResource
{
    public function __construct(protected array $metrics)
    {
    }

    public static function make(array $metrics): static
    {
        return new static($metrics);
    }

    abstract public function toResponse(): Response;
}
