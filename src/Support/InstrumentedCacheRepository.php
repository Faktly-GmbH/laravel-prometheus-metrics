<?php

namespace Faktly\LaravelPrometheusMetrics\Support;

use Closure;
use Faktly\LaravelPrometheusMetrics\Metrics\Cache\CacheMetricsStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class InstrumentedCacheRepository implements CacheRepository
{
    public function __construct(
        protected CacheRepository $inner,
        protected CacheMetricsStore $metrics
    ) {
    }

    public function put($key, $value, $ttl = null)
    {
        $this->metrics->write();

        return $this->inner->put($key, $value, $ttl);
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->metrics->write();

        return $this->inner->set($key, $value, $ttl);
    }

    public function forever($key, $value)
    {
        $this->metrics->write();

        return $this->inner->forever($key, $value);
    }

    public function forget($key)
    {
        $this->metrics->delete();

        return $this->inner->forget($key);
    }

    public function delete($key): bool
    {
        $this->metrics->delete();

        return $this->inner->delete($key);
    }

    public function add($key, $value, $ttl = null)
    {
        return $this->inner->add($key, $value, $ttl);
    }

    public function increment($key, $value = 1)
    {
        return $this->inner->increment($key, $value);
    }

    public function decrement($key, $value = 1)
    {
        return $this->inner->decrement($key, $value);
    }

    public function has($key): bool
    {
        return $this->inner->has($key);
    }

    public function pull($key, $default = null)
    {
        $value = $this->get($key, $default);

        // delete() already increments delete counter
        $this->delete($key);

        return $value;
    }

    public function get($key, $default = null): mixed
    {
        $value = $this->inner->get($key, $default);

        // If the inner store returned the default value, treat as miss.
        if ($value === $default) {
            $this->metrics->miss();
        } else {
            $this->metrics->hit();
        }

        return $value;
    }

    public function remember($key, $ttl, Closure $callback)
    {
        return $this->inner->remember($key, $ttl, $callback);
    }

    public function sear($key, Closure $callback)
    {
        return $this->inner->sear($key, $callback);
    }

    public function rememberForever($key, Closure $callback)
    {
        return $this->inner->rememberForever($key, $callback);
    }

    public function getMultiple($keys, $default = null): iterable
    {
        return $this->inner->getMultiple($keys, $default);
    }

    public function deleteMultiple($keys): bool
    {
        return $this->inner->deleteMultiple($keys);
    }

    public function setMultiple($values, $ttl = null): bool
    {
        return $this->inner->setMultiple($values, $ttl);
    }

    public function clear(): bool
    {
        return $this->inner->clear();
    }

    public function flush(): bool
    {
        return $this->inner->flush();
    }

    public function getStore()
    {
        return $this->inner->getStore();
    }
}
