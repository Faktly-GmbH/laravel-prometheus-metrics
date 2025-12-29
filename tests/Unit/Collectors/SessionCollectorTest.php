<?php

namespace Faktly\LaravelPrometheusMetrics\Tests\Unit\Collectors;

use Faktly\LaravelPrometheusMetrics\Collectors\SessionCollector;
use Faktly\LaravelPrometheusMetrics\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Mockery;

class SessionCollectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('prometheus-metrics.collectors.config.session.enabled', true);

        Config::set('session.driver', 'file');
        Config::set('session.lifetime', 120);
        Config::set('session.table', 'sessions');
        Config::set('session.connection', 'default');
        Config::set('session.prefix', 'LARAVEL_SESSION:');
    }

    public function test_returns_empty_array_when_disabled(): void
    {
        Config::set('prometheus-metrics.collectors.config.session.enabled', false);

        $result = (new SessionCollector())->collect();

        $this->assertSame([], $result);
    }

    public function test_database_driver_returns_zero_when_table_missing(): void
    {
        Config::set('session.driver', 'database');

        Schema::shouldReceive('hasTable')->with('sessions')->andReturn(false);

        $result = (new SessionCollector())->collect();

        $this->assertSame('database', $result['driver']);
        $this->assertSame(0, $result['active_count']);
    }

    public function test_database_driver_counts_active_sessions(): void
    {
        Config::set('session.driver', 'database');
        Config::set('session.lifetime', 120);

        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));

        Schema::shouldReceive('hasTable')->with('sessions')->andReturn(true);

        $query = Mockery::mock();
        $query->shouldReceive('where')
              ->with('last_activity', '>=', Mockery::type('int'))
              ->once()
              ->andReturnSelf();
        $query->shouldReceive('count')->once()->andReturn(5);

        DB::shouldReceive('table')->with('sessions')->once()->andReturn($query);

        $result = (new SessionCollector())->collect();

        $this->assertSame('database', $result['driver']);
        $this->assertSame(5, $result['active_count']);
    }

    public function test_redis_driver_counts_sessions_via_scan_single_batch(): void
    {
        Config::set('session.driver', 'redis');
        Config::set('session.connection', 'default');
        Config::set('session.prefix', 'LARAVEL_SESSION:');

        $redisConn = Mockery::mock();

        // One iteration: cursor goes back to '0' immediately, 3 keys.
        $redisConn->shouldReceive('scan')
                  ->once()
                  ->with('0', Mockery::on(function ($opts) {
                      return is_array($opts)
                          && ($opts['match'] ?? null) === 'LARAVEL_SESSION:*'
                          && isset($opts['count']);
                  }))
                  ->andReturn(['0', ['k1', 'k2', 'k3']]);

        Redis::shouldReceive('connection')->with('default')->once()->andReturn($redisConn);

        $result = (new SessionCollector())->collect();

        $this->assertSame('redis', $result['driver']);
        $this->assertSame(3, $result['active_count']);
    }

    public function test_redis_driver_counts_sessions_via_scan_multiple_batches(): void
    {
        Config::set('session.driver', 'redis');
        Config::set('session.connection', 'default');
        Config::set('session.prefix', 'LARAVEL_SESSION:');

        $redisConn = Mockery::mock();

        $redisConn->shouldReceive('scan')
                  ->once()
                  ->andReturn(['5', ['k1', 'k2']]);

        $redisConn->shouldReceive('scan')
                  ->once()
                  ->andReturn(['0', ['k3']]);

        Redis::shouldReceive('connection')->with('default')->once()->andReturn($redisConn);

        $result = (new SessionCollector())->collect();

        $this->assertSame('redis', $result['driver']);
        $this->assertSame(3, $result['active_count']);
    }

    public function test_file_driver_counts_only_non_expired_files(): void
    {
        Config::set('session.driver', 'file');
        Config::set('session.lifetime', 120);

        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));

        $base = sys_get_temp_dir() . '/lpm_sessions_' . uniqid('', true);
        $sessionDir = $base . '/framework/sessions';
        @mkdir($sessionDir, 0777, true);

        $fresh = $sessionDir . '/fresh.sess';
        $old = $sessionDir . '/old.sess';

        file_put_contents($fresh, 'x');
        file_put_contents($old, 'x');

        touch($fresh, Carbon::now()->subMinutes(10)->timestamp);
        touch($old, Carbon::now()->subMinutes(200)->timestamp);

        $this->app->useStoragePath($base);

        $result = (new SessionCollector())->collect();

        $this->assertSame('file', $result['driver']);
        $this->assertSame(1, $result['active_count']);
    }

    public function test_memcached_driver_returns_zero(): void
    {
        Config::set('session.driver', 'memcached');

        $result = (new SessionCollector())->collect();

        $this->assertSame('memcached', $result['driver']);
        $this->assertSame(0, $result['active_count']);
    }

    public function test_array_driver_returns_zero(): void
    {
        Config::set('session.driver', 'array');

        $result = (new SessionCollector())->collect();

        $this->assertSame('array', $result['driver']);
        $this->assertSame(0, $result['active_count']);
    }

    public function test_cookie_driver_returns_zero(): void
    {
        Config::set('session.driver', 'cookie');

        $result = (new SessionCollector())->collect();

        $this->assertSame('cookie', $result['driver']);
        $this->assertSame(0, $result['active_count']);
    }
}
