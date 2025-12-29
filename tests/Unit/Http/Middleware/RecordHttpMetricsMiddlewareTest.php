<?php

namespace Faktly\LaravelPrometheusMetrics\Tests\Unit\Http\Middleware;

use Faktly\LaravelPrometheusMetrics\Http\Middleware\RecordHttpMetricsMiddleware;
use Faktly\LaravelPrometheusMetrics\Metrics\Request\CacheMetricsStore;
use Faktly\LaravelPrometheusMetrics\Tests\TestCase;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

class RecordHttpMetricsMiddlewareTest extends TestCase
{
    private CacheMetricsStore $store;
    private RecordHttpMetricsMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $cacheStore = new Repository(new ArrayStore());
        $this->store = new CacheMetricsStore($cacheStore);
        $this->middleware = new RecordHttpMetricsMiddleware($this->store);
    }

    public function test_middleware_skips_when_disabled(): void
    {
        Config::set('prometheus-metrics.collectors.config.http.enabled', false);

        $request = Request::create('/api/posts', 'GET');
        $response = new Response('test', 200);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $this->assertSame($response, $result);

        $metrics = $this->store->getMetrics();
        $this->assertEmpty($metrics['requests_total'] ?? []);
    }

    public function test_middleware_records_request_count(): void
    {
        Config::set('prometheus-metrics.collectors.config.http.enabled', true);

        $request = Request::create('/api/posts', 'GET');
        $response = new Response('test', 200);

        $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $metrics = $this->store->getMetrics();

        $this->assertArrayHasKey('requests_total', $metrics);
        $this->assertArrayHasKey('GET:/api/posts:200', $metrics['requests_total']);
        $this->assertSame(1, $metrics['requests_total']['GET:/api/posts:200']);
    }

    public function test_middleware_records_duration(): void
    {
        Config::set('prometheus-metrics.collectors.config.http.enabled', true);

        $request = Request::create('/api/posts', 'GET');
        $response = new Response('test', 200);

        $this->middleware->handle($request, function () use ($response) {
            usleep(100000); // Sleep 100ms
            return $response;
        });

        $metrics = $this->store->getMetrics();

        $this->assertArrayHasKey('request_duration', $metrics);
        $this->assertArrayHasKey('GET:/api/posts:200', $metrics['request_duration']);

        $samples = $metrics['request_duration']['GET:/api/posts:200'];

        // samples is an array of individual measurements
        $this->assertIsArray($samples);
        $this->assertCount(1, $samples);
        $this->assertGreaterThanOrEqual(0.1, $samples[0]); // At least 100ms
    }

    public function test_middleware_records_request_size(): void
    {
        Config::set('prometheus-metrics.collectors.config.http.enabled', true);

        $request = Request::create('/api/posts', 'POST', [], [], [], [], 'test body content');
        $response = new Response('test', 201);

        $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $metrics = $this->store->getMetrics();

        // Request size sollte body length sein
        $this->assertArrayHasKey('request_size_bytes', $metrics);
        $this->assertArrayHasKey('POST:/api/posts', $metrics['request_size_bytes']);

        $samples = $metrics['request_size_bytes']['POST:/api/posts'];
        $this->assertCount(1, $samples);
        $this->assertSame(17, $samples[0]); // strlen('test body content')
    }

    public function test_middleware_records_response_size(): void
    {
        Config::set('prometheus-metrics.collectors.config.http.enabled', true);

        $testContent = 'test response content';
        $request = Request::create('/api/posts', 'GET');
        $response = new Response($testContent);

        $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $metrics = $this->store->getMetrics();

        $samples = $metrics['response_size_bytes']['GET:/api/posts'];
        $this->assertCount(1, $samples);
        // Assert actual length
        $this->assertSame(strlen($testContent), $samples[0]);
    }

    public function test_middleware_handles_different_status_codes(): void
    {
        Config::set('prometheus-metrics.collectors.config.http.enabled', true);

        $statusCodes = [200, 201, 400, 404, 500];

        foreach ($statusCodes as $status) {
            $request = Request::create('/api/posts', 'GET');
            $response = new Response('test', $status);

            $this->middleware->handle($request, function () use ($response) {
                return $response;
            });
        }

        $metrics = $this->store->getMetrics();

        $this->assertSame(1, $metrics['requests_total']['GET:/api/posts:200']);
        $this->assertSame(1, $metrics['requests_total']['GET:/api/posts:201']);
        $this->assertSame(1, $metrics['requests_total']['GET:/api/posts:400']);
        $this->assertSame(1, $metrics['requests_total']['GET:/api/posts:404']);
        $this->assertSame(1, $metrics['requests_total']['GET:/api/posts:500']);
    }

    public function test_middleware_handles_root_path(): void
    {
        Config::set('prometheus-metrics.collectors.config.http.enabled', true);

        $request = Request::create('/', 'GET');
        $response = new Response('test', 200);

        $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $metrics = $this->store->getMetrics();

        $this->assertArrayHasKey('GET:/:200', $metrics['requests_total']);
        $this->assertSame(1, $metrics['requests_total']['GET:/:200']);
    }

    public function test_middleware_aggregates_multiple_requests(): void
    {
        Config::set('prometheus-metrics.collectors.config.http.enabled', true);

        for ($i = 0; $i < 5; $i++) {
            $request = Request::create('/api/posts', 'GET');
            $response = new Response('test', 200);

            $this->middleware->handle($request, function () use ($response) {
                return $response;
            });
        }

        $metrics = $this->store->getMetrics();

        $this->assertSame(5, $metrics['requests_total']['GET:/api/posts:200']);
    }

    public function test_middleware_ignores_zero_content_length(): void
    {
        Config::set('prometheus-metrics.collectors.config.http.enabled', true);

        $request = Request::create('/api/posts', 'GET');
        $response = new Response(''); // Empty response

        $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $metrics = $this->store->getMetrics();

        // Request count sollte dennoch recorded sein
        $this->assertSame(1, $metrics['requests_total']['GET:/api/posts:200']);

        // Aber response_size sollte NOT recorded sein (empty)
        $this->assertArrayNotHasKey('GET:/api/posts', $metrics['response_size_bytes'] ?? []);
    }
}
