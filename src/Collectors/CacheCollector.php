<?php

namespace Faktly\LaravelPrometheusMetrics\Collectors;

use Faktly\LaravelPrometheusMetrics\Metrics\Cache\CacheMetricsStore;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use stdClass;
use Throwable;

class CacheCollector extends BaseCollector
{
    public function collect(): array
    {
        if (!$this->isEnabled('cache')) {
            return [];
        }

        try {
            $defaultStore = Config::get('cache.default', 'file');
            $storesConfig = Config::get('cache.stores', []);

            $defaultRepository = Cache::store($defaultStore);

            $driver = $this->getDriverName($defaultRepository);
            $supportsTags = $defaultRepository->getStore() instanceof TaggableStore;

            $stores = $this->formatStores($storesConfig);

            $metrics = [
                'default_store'  => $defaultStore,
                'default_driver' => $driver,
                'supports_tags'  => $supportsTags,
                'stores'         => $stores,
            ];

            if (Config::get('prometheus-metrics.collectors.config.cache.track_operations', false)) {
                /** @var CacheMetricsStore $metricsStore */
                $metricsStore = app(CacheMetricsStore::class);

                $metrics['operations'] = $metricsStore->snapshot();
            }

            return $metrics;
        } catch (Throwable $e) {
            $this->handleException('CacheCollector', $e);

            return [
                'default_store'  => Config::get('cache.default', 'file'),
                'default_driver' => null,
                'supports_tags'  => false,
                'stores'         => new stdClass(),
                'error'          => $e->getMessage(),
            ];
        }
    }

    protected function isEnabled($feature): bool
    {
        return (bool)Config::get('prometheus-metrics.collectors.config.cache.enabled', true);
    }

    private function getDriverName(CacheRepository $repository): string
    {
        $store = $repository->getStore();

        $knownDrivers = [
            'apc',
            'array',
            'database',
            'file',
            'memcached',
            'redis',
            'dynamodb',
            'null',
            'octane',
            'failover',
        ];

        $driver = method_exists($store, 'getDriver')
            ? $store->getDriver()
            : null;

        if (is_string($driver) && in_array($driver, $knownDrivers, true)) {
            return $driver;
        }

        return class_basename($store);
    }

    private function formatStores(array $storesConfig)
    {
        if ($storesConfig === []) {
            return new stdClass();
        }

        $result = [];

        foreach ($storesConfig as $name => $config) {
            $driver = Arr::get($config, 'driver', 'unknown');

            $supportsTags = false;

            try {
                $repository = Cache::store($name);
                $supportsTags = $repository->getStore() instanceof TaggableStore;
            } catch (Throwable $e) {
                $this->handleException('CacheCollector.store.' . $name, $e);
            }

            $result[$name] = [
                'driver'        => $driver,
                'supports_tags' => $supportsTags,
                'failover'      => $driver === 'failover'
                    ? (array)Arr::get($config, 'stores', [])
                    : [],
            ];
        }

        return $result ?: new stdClass();
    }

    public function getName(): string
    {
        return 'cache';
    }
}
