<?php

namespace Faktly\LaravelPrometheusMetrics\Tests\Unit\Collectors;

use Faktly\LaravelPrometheusMetrics\Collectors\HTTPCollector;
use Faktly\LaravelPrometheusMetrics\Metrics\Request\CacheMetricsStore;
use Faktly\LaravelPrometheusMetrics\Tests\TestCase;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Config;

class HTTPCollectorTest extends TestCase
{
    public function test_returns_empty_array_when_disabled(): void
    {
        Config::set('prometheus-metrics.collectors.config.http.enabled', false);
        $store = new CacheMetricsStore(new Repository(new ArrayStore()));
        $collector = new HTTPCollector($store);

        $result = $collector->collect();

        $this->assertSame([], $result);
    }

    public function test_collect_returns_empty_arrays_when_no_metrics(): void
    {
        $store = new CacheMetricsStore(new Repository(new ArrayStore()));
        $collector = new HTTPCollector($store);

        $result = $collector->collect();

        $this->assertArrayHasKey('requests_total', $result);
        $this->assertArrayHasKey('request_duration_seconds', $result);
        $this->assertArrayHasKey('request_size_bytes', $result);
        $this->assertArrayHasKey('response_size_bytes', $result);

        $this->assertEmpty($result['requests_total']);
        $this->assertEmpty($result['request_duration_seconds']);
        $this->assertEmpty($result['request_size_bytes']);
        $this->assertEmpty($result['response_size_bytes']);
    }

    public function test_collect_aggregates_request_counts(): void
    {
        $store = new CacheMetricsStore(new Repository(new ArrayStore()));
        $store->incrementRequestCount('GET', '/api/posts', 200);
        $store->incrementRequestCount('GET', '/api/posts', 200);
        $store->incrementRequestCount('POST', '/api/posts', 201);
        $store->incrementRequestCount('GET', '/api/posts/1', 404);

        $collector = new HTTPCollector($store);
        $result = $collector->collect();

        $this->assertIsArray($result['requests_total']);
        $this->assertArrayHasKey('GET:/api/posts:200', $result['requests_total']);
        $this->assertSame(2, $result['requests_total']['GET:/api/posts:200']);
        $this->assertSame(1, $result['requests_total']['POST:/api/posts:201']);
        $this->assertSame(1, $result['requests_total']['GET:/api/posts/1:404']);
    }

    public function test_collect_builds_duration_histogram(): void
    {
        $store = new CacheMetricsStore(new Repository(new ArrayStore()));
        $store->recordRequestDuration('GET', '/api/posts', 200, 50);
        $store->recordRequestDuration('GET', '/api/posts', 200, 100);
        $store->recordRequestDuration('GET', '/api/posts', 200, 200);

        $collector = new HTTPCollector($store);
        $result = $collector->collect();

        $this->assertIsArray($result['request_duration_seconds']);
        $this->assertArrayHasKey('GET:/api/posts:200', $result['request_duration_seconds']);

        $histogram = $result['request_duration_seconds']['GET:/api/posts:200'];

        $this->assertArrayHasKey('0.05', $histogram);
        $this->assertArrayHasKey('0.1', $histogram);
        $this->assertArrayHasKey('0.25', $histogram);

        $this->assertSame(1, $histogram['0.05']);
        $this->assertSame(2, $histogram['0.1']);
        $this->assertSame(3, $histogram['0.25']);

        $this->assertSame(3, $histogram['total']);
        $this->assertEqualsWithDelta(0.35, $histogram['sum'], 0.001);
    }

    public function test_collect_aggregates_request_sizes(): void
    {
        $store = new CacheMetricsStore(new Repository(new ArrayStore()));
        $store->recordRequestSize('POST', '/api/posts', 1024);
        $store->recordRequestSize('POST', '/api/posts', 2048);
        $store->recordRequestSize('PUT', '/api/posts/1', 512);

        $collector = new HTTPCollector($store);
        $result = $collector->collect();

        $this->assertIsArray($result['request_size_bytes']);
        $this->assertArrayHasKey('POST:/api/posts', $result['request_size_bytes']);

        $histogram = $result['request_size_bytes']['POST:/api/posts'];
        $this->assertSame(2, $histogram['total']);
        $this->assertEquals(3072, $histogram['sum']);
    }

    public function test_collect_aggregates_response_sizes(): void
    {
        $store = new CacheMetricsStore(new Repository(new ArrayStore()));
        $store->recordResponseSize('GET', '/api/posts', 4096);
        $store->recordResponseSize('GET', '/api/posts', 8192);

        $collector = new HTTPCollector($store);
        $result = $collector->collect();

        $this->assertIsArray($result['response_size_bytes']);
        $this->assertArrayHasKey('GET:/api/posts', $result['response_size_bytes']);

        $histogram = $result['response_size_bytes']['GET:/api/posts'];
        $this->assertSame(2, $histogram['total']);
        $this->assertEquals(12288, $histogram['sum']);
    }

    public function test_collect_request_size_histogram_structure(): void
    {
        $store = new CacheMetricsStore(new Repository(new ArrayStore()));
        $store->recordRequestSize('POST', '/api/posts', 500);
        $store->recordRequestSize('POST', '/api/posts', 5000);
        $store->recordRequestSize('POST', '/api/posts', 50000);
        $store->recordRequestSize('POST', '/api/posts', 500000);

        $collector = new HTTPCollector($store);
        $result = $collector->collect();

        $this->assertIsArray($result['request_size_bytes']);
        $this->assertArrayHasKey('POST:/api/posts', $result['request_size_bytes']);

        $histogram = $result['request_size_bytes']['POST:/api/posts'];

        $this->assertArrayHasKey('100', $histogram);
        $this->assertArrayHasKey('1000', $histogram);
        $this->assertArrayHasKey('10000', $histogram);
        $this->assertArrayHasKey('100000', $histogram);
        $this->assertArrayHasKey('1000000', $histogram);

        $this->assertSame(0, $histogram['100']);
        $this->assertSame(1, $histogram['1000']);
        $this->assertSame(2, $histogram['10000']);
        $this->assertSame(3, $histogram['100000']);
        $this->assertSame(4, $histogram['1000000']);

        $this->assertSame(4, $histogram['total']);
        $this->assertEquals(555500, $histogram['sum']);
    }

    public function test_collect_response_size_histogram_structure(): void
    {
        $store = new CacheMetricsStore(new Repository(new ArrayStore()));
        $store->recordResponseSize('GET', '/api/posts', 200);
        $store->recordResponseSize('GET', '/api/posts', 2000);
        $store->recordResponseSize('GET', '/api/posts', 20000);
        $store->recordResponseSize('GET', '/api/posts', 200000);

        $collector = new HTTPCollector($store);
        $result = $collector->collect();

        $this->assertIsArray($result['response_size_bytes']);
        $this->assertArrayHasKey('GET:/api/posts', $result['response_size_bytes']);

        $histogram = $result['response_size_bytes']['GET:/api/posts'];

        $this->assertArrayHasKey('100', $histogram);
        $this->assertArrayHasKey('1000', $histogram);
        $this->assertArrayHasKey('10000', $histogram);
        $this->assertArrayHasKey('100000', $histogram);
        $this->assertArrayHasKey('1000000', $histogram);

        $this->assertSame(0, $histogram['100']);
        $this->assertSame(1, $histogram['1000']);
        $this->assertSame(2, $histogram['10000']);
        $this->assertSame(3, $histogram['100000']);
        $this->assertSame(4, $histogram['1000000']);

        $this->assertSame(4, $histogram['total']);
        $this->assertEquals(222200, $histogram['sum']);
    }

    public function test_store_keeps_only_last_1000_samples(): void
    {
        $store = new CacheMetricsStore(new Repository(new ArrayStore()));

        for ($i = 0; $i < 1100; $i++) {
            $store->recordRequestDuration('GET', '/api/test', 200, 50 + $i);
        }

        $metrics = $store->getMetrics();
        $samples = $metrics['request_duration']['GET:/api/test:200'] ?? [];

        $this->assertCount(1000, $samples);
    }

    public function test_request_size_keeps_only_last_1000_samples(): void
    {
        $store = new CacheMetricsStore(new Repository(new ArrayStore()));

        for ($i = 0; $i < 1100; $i++) {
            $store->recordRequestSize('POST', '/api/test', 1000 + $i);
        }

        $metrics = $store->getMetrics();
        $samples = $metrics['request_size_bytes']['POST:/api/test'] ?? [];

        $this->assertCount(1000, $samples);
        $this->assertSame(2099, end($samples));
    }

    public function test_response_size_keeps_only_last_1000_samples(): void
    {
        $store = new CacheMetricsStore(new Repository(new ArrayStore()));

        for ($i = 0; $i < 1100; $i++) {
            $store->recordResponseSize('GET', '/api/test', 2000 + $i);
        }

        $metrics = $store->getMetrics();
        $samples = $metrics['response_size_bytes']['GET:/api/test'] ?? [];

        $this->assertCount(1000, $samples);
        $this->assertSame(3099, end($samples));
    }

    public function test_separate_request_and_response_sizes(): void
    {
        $store = new CacheMetricsStore(new Repository(new ArrayStore()));
        $store->recordRequestSize('POST', '/api/posts', 1024);
        $store->recordResponseSize('POST', '/api/posts', 2048);

        $collector = new HTTPCollector($store);
        $result = $collector->collect();

        $this->assertArrayHasKey('POST:/api/posts', $result['request_size_bytes']);
        $this->assertArrayHasKey('POST:/api/posts', $result['response_size_bytes']);

        $this->assertEquals(1024, $result['request_size_bytes']['POST:/api/posts']['sum']);
        $this->assertEquals(2048, $result['response_size_bytes']['POST:/api/posts']['sum']);
    }

    public function test_request_and_response_size_zero_handling(): void
    {
        $store = new CacheMetricsStore(new Repository(new ArrayStore()));
        $store->recordRequestSize('GET', '/api/test', 0);
        $store->recordResponseSize('GET', '/api/test', 0);

        $metrics = $store->getMetrics();

        $this->assertArrayHasKey('GET:/api/test', $metrics['request_size_bytes']);
        $this->assertArrayHasKey('GET:/api/test', $metrics['response_size_bytes']);

        $this->assertEquals(0, $metrics['request_size_bytes']['GET:/api/test'][0]);
        $this->assertEquals(0, $metrics['response_size_bytes']['GET:/api/test'][0]);
    }

    public function test_handles_different_status_codes(): void
    {
        $store = new CacheMetricsStore(new Repository(new ArrayStore()));

        $statusCodes = [200, 201, 400, 404, 500];

        foreach ($statusCodes as $status) {
            $store->incrementRequestCount('GET', '/api/posts', $status);
        }

        $collector = new HTTPCollector($store);
        $result = $collector->collect();

        $this->assertSame(1, $result['requests_total']['GET:/api/posts:200']);
        $this->assertSame(1, $result['requests_total']['GET:/api/posts:201']);
        $this->assertSame(1, $result['requests_total']['GET:/api/posts:400']);
        $this->assertSame(1, $result['requests_total']['GET:/api/posts:404']);
        $this->assertSame(1, $result['requests_total']['GET:/api/posts:500']);
    }
}
