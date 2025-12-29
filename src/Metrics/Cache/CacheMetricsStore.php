<?php

namespace Faktly\LaravelPrometheusMetrics\Metrics\Cache;

class CacheMetricsStore
{
    protected int $hits = 0;

    protected int $misses = 0;

    protected int $writes = 0;

    protected int $deletes = 0;

    public function hit(): void
    {
        $this->hits++;
    }

    public function miss(): void
    {
        $this->misses++;
    }

    public function write(): void
    {
        $this->writes++;
    }

    public function delete(): void
    {
        $this->deletes++;
    }

    public function snapshot(): array
    {
        return [
            'hits'    => $this->hits,
            'misses'  => $this->misses,
            'writes'  => $this->writes,
            'deletes' => $this->deletes,
        ];
    }
}
