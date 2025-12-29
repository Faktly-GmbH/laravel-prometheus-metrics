<?php

namespace Faktly\LaravelPrometheusMetrics\Http\Resource;

use Faktly\LaravelPrometheusMetrics\Support\Output\MetricsResource;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

class YamlMetricsResource extends MetricsResource
{
    public function toResponse(): Response
    {
        if (!class_exists(Yaml::class)) {
            return new Response('YAML support not installed', 500);
        }

        $yaml = Yaml::dump($this->metrics, 4, 2);

        return new Response(
            $yaml,
            200,
            // ['Content-Type' => 'application/x-yaml']
            ['Content-Type' => 'text/plain; version=0.0.4']
        );
    }
}
