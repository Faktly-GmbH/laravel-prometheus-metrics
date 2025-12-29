<?php

namespace Faktly\LaravelPrometheusMetrics\Support;

use Faktly\LaravelPrometheusMetrics\Metrics\Cache\CacheMetricsStore;
use Illuminate\Cache\CacheManager as BaseCacheManager;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Store;

class InstrumentedCacheManager extends BaseCacheManager
{
    protected CacheMetricsStore $metrics;

    public function setMetricsStore(CacheMetricsStore $metrics): void
    {
        $this->metrics = $metrics;
    }

    /**
     * Override to wrap created repositories.
     *
     * @param Store $store
     */
    public function repository($store, array $config = []): CacheRepository
    {
        $repository = parent::repository($store);

        if (!isset($this->metrics)) {
            return $repository;
        }

        return new InstrumentedCacheRepository($repository, $this->metrics);
    }
}
