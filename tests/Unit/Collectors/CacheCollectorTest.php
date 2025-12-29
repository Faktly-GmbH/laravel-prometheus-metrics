<?php

namespace Faktly\LaravelPrometheusMetrics\Tests\Unit\Collectors;

use Faktly\LaravelPrometheusMetrics\Collectors\CacheCollector;
use Faktly\LaravelPrometheusMetrics\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class CacheCollectorTest extends TestCase
{
    public function test_it_returns_empty_array_when_disabled(): void
    {
        Config::set('prometheus-metrics.collectors.config.cache.enabled', false);

        $collector = $this->app->make(CacheCollector::class);

        $this->assertSame([], $collector->collect());
    }

    public function test_it_collects_basic_store_information(): void
    {
        Config::set('prometheus-metrics.collectors.config.cache.enabled', true);
        Config::set('cache.default', 'array');

        $collector = $this->app->make(CacheCollector::class);
        $result = $collector->collect();

        $this->assertArrayHasKey('default_store', $result);
        $this->assertArrayHasKey('default_driver', $result);
        $this->assertArrayHasKey('supports_tags', $result);
        $this->assertArrayHasKey('stores', $result);

        $this->assertSame('array', $result['default_store']);
        $this->assertIsArray($result['stores']);
        $this->assertArrayHasKey('array', $result['stores']);
        $this->assertSame('array', $result['stores']['array']['driver']);
    }

    public function test_it_reports_operation_counters_when_enabled(): void
    {
        Config::set('prometheus-metrics.collectors.config.cache.enabled', true);
        Config::set('prometheus-metrics.collectors.config.cache.track_operations', true);
        Config::set('cache.default', 'array');

        // cause one miss and one hit
        Cache::forget('metrics_test_key');
        Cache::get('metrics_test_key');                // miss (returns null, default is null)
        Cache::put('metrics_test_key', 'value', 60);   // write
        Cache::get('metrics_test_key');                // hit
        Cache::forget('metrics_test_key');             // delete

        $collector = $this->app->make(CacheCollector::class);
        $result = $collector->collect();

        $operations = $result['operations'];

        $this->assertArrayHasKey('hits', $operations);
        $this->assertArrayHasKey('misses', $operations);
        $this->assertArrayHasKey('writes', $operations);
        $this->assertArrayHasKey('deletes', $operations);

        $this->assertGreaterThanOrEqual(1, $operations['misses']);
        $this->assertGreaterThanOrEqual(1, $operations['writes']);
        $this->assertGreaterThanOrEqual(1, $operations['hits']);
        $this->assertGreaterThanOrEqual(1, $operations['deletes']);
    }

    public function test_it_does_not_include_operations_when_tracking_disabled(): void
    {
        Config::set('prometheus-metrics.collectors.config.cache.enabled', true);
        Config::set('prometheus-metrics.collectors.config.cache.track_operations', false);
        Config::set('cache.default', 'array');

        Cache::put('metrics_test_key', 'value', 60);

        $collector = $this->app->make(CacheCollector::class);
        $result = $collector->collect();

        $this->assertArrayNotHasKey('operations', $result);
    }
}
