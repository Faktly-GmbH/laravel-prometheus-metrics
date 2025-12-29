<?php

namespace Faktly\LaravelPrometheusMetrics\Tests\Unit\Collectors;

use Faktly\LaravelPrometheusMetrics\Collectors\QueueCollector;
use Faktly\LaravelPrometheusMetrics\Tests\TestCase;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use stdClass;

class QueueCollectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('prometheus-metrics.collectors.config.queue.enabled', true);
        Config::set('prometheus-metrics.collectors.config.queue.include_per_queue_breakdown', true);

        Config::set('queue.default', 'database');
        Config::set('queue.connections.database.table', 'jobs');
        Config::set('queue.failed.table', 'failed_jobs');
    }

    public function test_returns_empty_array_when_disabled(): void
    {
        Config::set('prometheus-metrics.collectors.config.queue.enabled', false);

        $result = (new QueueCollector())->collect();

        $this->assertSame([], $result);
    }

    public function test_returns_zeros_when_driver_is_not_database(): void
    {
        Config::set('queue.default', 'redis');

        $result = (new QueueCollector())->collect();

        $this->assertSame(0, $result['pending_count']);
        $this->assertSame(0, $result['failed_count']);
        $this->assertSame('redis', $result['driver']);
        $this->assertArrayNotHasKey('pending_per_queue', $result);
    }

    public function test_collect_returns_counts_and_per_queue_breakdown(): void
    {
        Schema::shouldReceive('hasTable')->with('jobs')->andReturn(true);
        Schema::shouldReceive('hasTable')->with('failed_jobs')->andReturn(true);

        DB::shouldReceive('raw')
          ->andReturnUsing(fn (string $sql) => new Expression($sql));

        // getPendingJobsCount()
        $pendingQuery = Mockery::mock();
        $pendingQuery->shouldReceive('where')->with('reserved_at', null)->once()->andReturnSelf();
        $pendingQuery->shouldReceive('count')->once()->andReturn(12);

        // getFailedJobsCount()
        $failedQuery = Mockery::mock();
        $failedQuery->shouldReceive('count')->once()->andReturn(3);

        // getPerQueueBreakdown()
        $breakdownQuery = Mockery::mock();
        $breakdownQuery->shouldReceive('where')->with('reserved_at', null)->once()->andReturnSelf();
        $breakdownQuery->shouldReceive('select')->once()->andReturnSelf();
        $breakdownQuery->shouldReceive('groupBy')->with('queue')->once()->andReturnSelf();

        $breakdownGet = Mockery::mock();
        $breakdownPluck = Mockery::mock();
        $breakdownQuery->shouldReceive('get')->once()->andReturn($breakdownGet);
        $breakdownGet->shouldReceive('pluck')->with('count', 'queue')->once()->andReturn($breakdownPluck);
        $breakdownPluck->shouldReceive('toArray')->once()->andReturn([
            'default' => 10,
            'emails'  => 2,
        ]);

        DB::shouldReceive('table')->with('jobs')->once()->ordered()->andReturn($pendingQuery);
        DB::shouldReceive('table')->with('failed_jobs')->once()->ordered()->andReturn($failedQuery);
        DB::shouldReceive('table')->with('jobs')->once()->ordered()->andReturn($breakdownQuery);

        $result = (new QueueCollector())->collect();

        $this->assertSame(12, $result['pending_count']);
        $this->assertSame(3, $result['failed_count']);
        $this->assertSame('database', $result['driver']);
        $this->assertSame(['default' => 10, 'emails' => 2], $result['pending_per_queue']);
    }

    public function test_collect_returns_empty_object_when_breakdown_is_enabled_but_query_returns_empty(): void
    {
        Schema::shouldReceive('hasTable')->with('jobs')->andReturn(true);
        Schema::shouldReceive('hasTable')->with('failed_jobs')->andReturn(true);

        DB::shouldReceive('raw')
          ->andReturnUsing(fn (string $sql) => new Expression($sql));

        $pendingQuery = Mockery::mock();
        $pendingQuery->shouldReceive('where')->with('reserved_at', null)->once()->andReturnSelf();
        $pendingQuery->shouldReceive('count')->once()->andReturn(0);

        $failedQuery = Mockery::mock();
        $failedQuery->shouldReceive('count')->once()->andReturn(0);

        $breakdownQuery = Mockery::mock();
        $breakdownQuery->shouldReceive('where')->with('reserved_at', null)->once()->andReturnSelf();
        $breakdownQuery->shouldReceive('select')->once()->andReturnSelf();
        $breakdownQuery->shouldReceive('groupBy')->with('queue')->once()->andReturnSelf();

        $breakdownGet = Mockery::mock();
        $breakdownPluck = Mockery::mock();
        $breakdownQuery->shouldReceive('get')->once()->andReturn($breakdownGet);
        $breakdownGet->shouldReceive('pluck')->with('count', 'queue')->once()->andReturn($breakdownPluck);
        $breakdownPluck->shouldReceive('toArray')->once()->andReturn([]);

        DB::shouldReceive('table')->with('jobs')->once()->ordered()->andReturn($pendingQuery);
        DB::shouldReceive('table')->with('failed_jobs')->once()->ordered()->andReturn($failedQuery);
        DB::shouldReceive('table')->with('jobs')->once()->ordered()->andReturn($breakdownQuery);

        $result = (new QueueCollector())->collect();

        $this->assertSame(0, $result['pending_count']);
        $this->assertSame(0, $result['failed_count']);
        $this->assertSame('database', $result['driver']);

        $this->assertArrayHasKey('pending_per_queue', $result);
        $this->assertEquals(new stdClass(), $result['pending_per_queue']);
    }

    public function test_collect_does_not_include_breakdown_when_disabled_in_config(): void
    {
        Config::set('prometheus-metrics.collectors.config.queue.include_per_queue_breakdown', false);

        Schema::shouldReceive('hasTable')->with('jobs')->andReturn(true);
        Schema::shouldReceive('hasTable')->with('failed_jobs')->andReturn(true);

        $pendingQuery = Mockery::mock();
        $pendingQuery->shouldReceive('where')->with('reserved_at', null)->once()->andReturnSelf();
        $pendingQuery->shouldReceive('count')->once()->andReturn(7);

        $failedQuery = Mockery::mock();
        $failedQuery->shouldReceive('count')->once()->andReturn(1);

        DB::shouldReceive('table')->with('jobs')->once()->ordered()->andReturn($pendingQuery);
        DB::shouldReceive('table')->with('failed_jobs')->once()->ordered()->andReturn($failedQuery);

        $result = (new QueueCollector())->collect();

        $this->assertSame(7, $result['pending_count']);
        $this->assertSame(1, $result['failed_count']);
        $this->assertSame('database', $result['driver']);
        $this->assertArrayNotHasKey('pending_per_queue', $result);
    }

    public function test_collect_returns_zero_counts_when_tables_do_not_exist(): void
    {
        Schema::shouldReceive('hasTable')->with('jobs')->andReturn(false);
        Schema::shouldReceive('hasTable')->with('failed_jobs')->andReturn(false);

        $result = (new QueueCollector())->collect();

        $this->assertSame(0, $result['pending_count']);
        $this->assertSame(0, $result['failed_count']);
        $this->assertSame('database', $result['driver']);
        $this->assertArrayNotHasKey('pending_per_queue', $result);
    }
}
