<?php

namespace Faktly\LaravelPrometheusMetrics\Tests\Unit\Collectors;

use Faktly\LaravelPrometheusMetrics\Collectors\HorizonCollector;
use Faktly\LaravelPrometheusMetrics\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Horizon;
use Mockery;
use stdClass;

class HorizonCollectorTest extends TestCase
{
    public function test_returns_empty_array_when_disabled(): void
    {
        Config::set('prometheus-metrics.collectors.config.horizon.enabled', false);

        $result = $this->createCollector()->collect();

        $this->assertSame([], $result);
    }

    protected function createCollector(): HorizonCollector
    {
        return new HorizonCollector();
    }

    public function test_returns_zero_values_when_redis_has_no_data(): void
    {
        if (!class_exists(Horizon::class)) {
            $this->markTestSkipped('laravel/horizon is not installed in this test runtime.');
        }

        $collector = $this->createCollector();

        $redisConn = Mockery::mock();

        // collect() calls connection() three times when per-queue is enabled:
        // jobs + processesTotal + processesPerQueue
        Redis::shouldReceive('connection')
             ->with('default')
             ->times(3)
             ->andReturn($redisConn);

        $redisConn->shouldReceive('get')
                  ->once()
                  ->with('horizon:metrics:jobs')
                  ->andReturn(null);

        // keys() is called twice (total + per-queue)
        $redisConn->shouldReceive('keys')
                  ->twice()
                  ->with('horizon:master:*:processes')
                  ->andReturn([], []);

        $result = $collector->collect();

        $this->assertSame(0.0, $result['jobs_per_minute']);
        $this->assertSame(0, $result['processed_total']);
        $this->assertEquals(new stdClass(), $result['processed_per_queue']);
    }

    public function test_returns_metrics_when_redis_has_data(): void
    {
        if (!class_exists(Horizon::class)) {
            $this->markTestSkipped('laravel/horizon is not installed in this test runtime.');
        }

        $collector = $this->createCollector();

        $redisConn = Mockery::mock();

        Redis::shouldReceive('connection')
             ->with('default')
             ->times(3)
             ->andReturn($redisConn);

        $redisConn->shouldReceive('get')
                  ->once()
                  ->with('horizon:metrics:jobs')
                  ->andReturn(json_encode(['jobs_per_minute' => 12.5]));

        $masterKey = 'horizon:master:instance-1:processes';

        // keys() called twice (total + per-queue)
        $redisConn->shouldReceive('keys')
                  ->twice()
                  ->with('horizon:master:*:processes')
                  ->andReturn([$masterKey], [$masterKey]);

        $processEntry1 = json_encode(['queue' => 'default']);
        $processEntry2 = json_encode(['queue' => 'high']);
        $processEntry3 = json_encode(['queue' => 'high']);

        // hgetall() called twice (total + per-queue)
        $redisConn->shouldReceive('hgetall')
                  ->twice()
                  ->with($masterKey)
                  ->andReturn(
                      ['p1' => $processEntry1, 'p2' => $processEntry2, 'p3' => $processEntry3],
                      ['p1' => $processEntry1, 'p2' => $processEntry2, 'p3' => $processEntry3],
                  );

        $result = $collector->collect();

        $this->assertSame(12.5, $result['jobs_per_minute']);
        $this->assertSame(3, $result['processed_total']);
        $this->assertSame(['default' => 1, 'high' => 2], $result['processed_per_queue']);
    }

    public function test_processed_per_queue_disabled_in_config(): void
    {
        if (!class_exists(Horizon::class)) {
            $this->markTestSkipped('laravel/horizon is not installed in this test runtime.');
        }

        Config::set('prometheus-metrics.collectors.config.horizon.include_processed_per_queue', false);

        $collector = $this->createCollector();

        $redisConn = Mockery::mock();

        // With per-queue disabled: connection() is called twice (jobs + processesTotal)
        Redis::shouldReceive('connection')
             ->with('default')
             ->times(2)
             ->andReturn($redisConn);

        $redisConn->shouldReceive('get')
                  ->once()
                  ->with('horizon:metrics:jobs')
                  ->andReturn(json_encode(['jobs_per_minute' => 5]));

        // Only processesTotal runs, so keys() called once
        $redisConn->shouldReceive('keys')
                  ->once()
                  ->with('horizon:master:*:processes')
                  ->andReturn([]);

        $result = $collector->collect();

        $this->assertSame(5.0, $result['jobs_per_minute']);
        $this->assertSame(0, $result['processed_total']);
        $this->assertEquals(new stdClass(), $result['processed_per_queue']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('prometheus-metrics.collectors.config.horizon.enabled', true);
        Config::set('prometheus-metrics.collectors.config.horizon.include_processed_per_queue', true);

        // Your collector uses this key
        Config::set('horizon.redis_connection', 'default');

        // Make key deterministic for the test
        Config::set('horizon.prefix', 'horizon');
    }
}
