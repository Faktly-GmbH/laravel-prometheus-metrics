<?php

namespace Faktly\LaravelPrometheusMetrics\Tests\Unit\Collectors;

use Faktly\LaravelPrometheusMetrics\Collectors\UserCollector;
use Faktly\LaravelPrometheusMetrics\Tests\TestCase;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Config;
use Mockery;

class UserCollectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('prometheus-metrics.collectors.config.user.enabled', true);
        Config::set('auth.providers.users.model', FakeUser::class);

        FakeUser::$newQueryQueue = [];
        FakeUser::$connectionMock = null;
    }

    public function test_returns_empty_array_when_disabled(): void
    {
        Config::set('prometheus-metrics.collectors.config.user.enabled', false);

        $result = (new UserCollector())->collect();

        $this->assertSame([], $result);
    }

    public function test_returns_error_when_user_model_not_found(): void
    {
        Config::set('auth.providers.users.model', 'App\\Models\\MissingUser');

        $result = (new UserCollector())->collect();

        $this->assertSame(0, $result['count']);
        $this->assertSame(0, $result['active_count']);
        $this->assertSame('App\\Models\\MissingUser', $result['model']);
        $this->assertSame('User model not found', $result['error']);
    }

    public function test_collect_counts_total_and_active_using_active_column(): void
    {
        $schemaBuilder = Mockery::mock(Builder::class);
        $schemaBuilder->shouldReceive('hasColumn')->with('users', 'active')->andReturn(true);
        $schemaBuilder->shouldReceive('hasColumn')->with('users', 'status')->andReturn(false);

        $conn = Mockery::mock(ConnectionInterface::class);
        $conn->shouldReceive('getSchemaBuilder')->andReturn($schemaBuilder);

        FakeUser::$connectionMock = $conn;

        $qTotal = Mockery::mock();
        $qTotal->shouldReceive('count')->once()->andReturn(10);

        $qActive = Mockery::mock();
        $qActive->shouldReceive('where')->with('active', true)->once()->andReturnSelf();
        $qActive->shouldReceive('count')->once()->andReturn(7);

        FakeUser::$newQueryQueue = [$qTotal, $qActive];

        $result = (new UserCollector())->collect();

        $this->assertSame(10, $result['count']);
        $this->assertSame(7, $result['active_count']);
        $this->assertSame(FakeUser::class, $result['model']);
    }

    public function test_collect_counts_active_using_status_column(): void
    {
        $schemaBuilder = Mockery::mock(Builder::class);
        $schemaBuilder->shouldReceive('hasColumn')->with('users', 'active')->andReturn(false);
        $schemaBuilder->shouldReceive('hasColumn')->with('users', 'status')->andReturn(true);

        $conn = Mockery::mock(ConnectionInterface::class);
        $conn->shouldReceive('getSchemaBuilder')->andReturn($schemaBuilder);

        FakeUser::$connectionMock = $conn;

        $qTotal = Mockery::mock();
        $qTotal->shouldReceive('count')->once()->andReturn(20);

        $qActive = Mockery::mock();
        $qActive->shouldReceive('where')->with('status', 'active')->once()->andReturnSelf();
        $qActive->shouldReceive('count')->once()->andReturn(4);

        FakeUser::$newQueryQueue = [$qTotal, $qActive];

        $result = (new UserCollector())->collect();

        $this->assertSame(20, $result['count']);
        $this->assertSame(4, $result['active_count']);
        $this->assertSame(FakeUser::class, $result['model']);
    }

    public function test_collect_falls_back_to_total_when_no_active_indicator_exists(): void
    {
        $schemaBuilder = Mockery::mock(Builder::class);
        $schemaBuilder->shouldReceive('hasColumn')->with('users', 'active')->andReturn(false);
        $schemaBuilder->shouldReceive('hasColumn')->with('users', 'status')->andReturn(false);

        $conn = Mockery::mock(ConnectionInterface::class);
        $conn->shouldReceive('getSchemaBuilder')->andReturn($schemaBuilder);

        FakeUser::$connectionMock = $conn;

        // 1) total count
        $qTotal = Mockery::mock();
        $qTotal->shouldReceive('count')->once()->andReturn(9);

        // 2) countActiveUsers() creates a query but does not use it in fallback
        $qUnused = Mockery::mock();

        // 3) fallback active_count uses countAllUsers() again
        $qTotalAgain = Mockery::mock();
        $qTotalAgain->shouldReceive('count')->once()->andReturn(9);

        FakeUser::$newQueryQueue = [$qTotal, $qUnused, $qTotalAgain];

        $result = (new UserCollector())->collect();

        $this->assertSame(9, $result['count']);
        $this->assertSame(9, $result['active_count']);
        $this->assertSame(FakeUser::class, $result['model']);
    }

}

class FakeUser extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    public $timestamps = false;

    public static $newQueryQueue = [];
    public static $connectionMock;

    public function getConnection()
    {
        return static::$connectionMock ?: parent::getConnection();
    }

    public function newQuery()
    {
        if (! empty(static::$newQueryQueue)) {
            return array_shift(static::$newQueryQueue);
        }

        return parent::newQuery();
    }

    public function getConnectionName()
    {
        return 'testing';
    }
}
