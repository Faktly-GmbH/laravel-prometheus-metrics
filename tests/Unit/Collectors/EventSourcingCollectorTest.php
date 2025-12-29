<?php

namespace Faktly\LaravelPrometheusMetrics\Tests\Unit\Collectors;

use App\Events\BarEvent;
use App\Events\BazEvent;
use App\Events\FooEvent;
use Faktly\LaravelPrometheusMetrics\Collectors\EventSourcingCollector;
use Faktly\LaravelPrometheusMetrics\Tests\TestCase;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Mockery;
use Spatie\EventSourcing\EventSourcingServiceProvider;
use stdClass;

class EventSourcingCollectorTest extends TestCase
{
    public function test_returns_empty_array_when_disabled(): void
    {
        Config::set('prometheus-metrics.collectors.config.event_sourcing.enabled', false);

        $result = (new EventSourcingCollector())->collect();

        $this->assertSame([], $result);
    }

    public function test_returns_defaults_when_spatie_event_sourcing_is_not_installed(): void
    {
        if (class_exists(EventSourcingServiceProvider::class)) {
            $this->markTestSkipped('spatie/laravel-event-sourcing is installed in this test runtime.');
        }

        Config::set('prometheus-metrics.collectors.config.event_sourcing.window_minutes', 60);

        $result = (new EventSourcingCollector())->collect();

        $this->assertSame(0, $result['events_count']);
        $this->assertSame(['3600s' => 0], $result['events_window_count']);
        $this->assertEquals(new stdClass(), $result['events_per_type_total']);
    }

    public function test_collect_returns_counts_window_and_per_type_breakdown(): void
    {
        if (!class_exists(EventSourcingServiceProvider::class)) {
            $this->markTestSkipped('spatie/laravel-event-sourcing is not installed in this test runtime.');
        }

        Config::set('prometheus-metrics.collectors.config.event_sourcing.window_minutes', 60);
        Config::set(
            'prometheus-metrics.collectors.config.event_sourcing.extra_stored_event_tables',
            ['stored_events_extra']
        );

        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));

        $schema = Mockery::mock(Builder::class);
        $schema->shouldReceive('hasTable')->with('stored_events')->andReturn(true);
        $schema->shouldReceive('hasTable')->with('stored_events_extra')->andReturn(true);
        DB::shouldReceive('getSchemaBuilder')->andReturn($schema);

        DB::shouldReceive('raw')
          ->andReturnUsing(fn (string $sql) => new Expression($sql));

        // total events count: stored_events (2) + stored_events_extra (3) = 5
        $totalQuery1 = Mockery::mock();
        $totalQuery1->shouldReceive('count')->once()->andReturn(2);

        $totalQuery2 = Mockery::mock();
        $totalQuery2->shouldReceive('count')->once()->andReturn(3);

        // window count (created_at >= from): stored_events (1) + extra (2) = 3
        $windowQuery1 = Mockery::mock();
        $windowQuery1->shouldReceive('where')
                     ->with('created_at', '>=', Mockery::type(Carbon::class))
                     ->once()
                     ->andReturnSelf();
        $windowQuery1->shouldReceive('count')->once()->andReturn(1);

        $windowQuery2 = Mockery::mock();
        $windowQuery2->shouldReceive('where')
                     ->with('created_at', '>=', Mockery::type(Carbon::class))
                     ->once()
                     ->andReturnSelf();
        $windowQuery2->shouldReceive('count')->once()->andReturn(2);

        // per type breakdown:
        // stored_events: Foo=2, Bar=1
        // extra: Foo=5, Baz=4
        // merged with class_basename: Foo=7, Bar=1, Baz=4
        $breakdownQuery1 = Mockery::mock();
        $breakdownQuery1->shouldReceive('select')->once()->andReturnSelf();
        $breakdownQuery1->shouldReceive('groupBy')->with('event_class')->once()->andReturnSelf();
        $breakdownGet1 = Mockery::mock();
        $breakdownPluck1 = Mockery::mock();
        $breakdownQuery1->shouldReceive('get')->once()->andReturn($breakdownGet1);
        $breakdownGet1->shouldReceive('pluck')->with('count', 'event_class')->once()->andReturn($breakdownPluck1);
        $breakdownPluck1->shouldReceive('toArray')->once()->andReturn([
            FooEvent::class => 2,
            BarEvent::class => 1,
        ]);

        $breakdownQuery2 = Mockery::mock();
        $breakdownQuery2->shouldReceive('select')->once()->andReturnSelf();
        $breakdownQuery2->shouldReceive('groupBy')->with('event_class')->once()->andReturnSelf();
        $breakdownGet2 = Mockery::mock();
        $breakdownPluck2 = Mockery::mock();
        $breakdownQuery2->shouldReceive('get')->once()->andReturn($breakdownGet2);
        $breakdownGet2->shouldReceive('pluck')->with('count', 'event_class')->once()->andReturn($breakdownPluck2);
        $breakdownPluck2->shouldReceive('toArray')->once()->andReturn([
            FooEvent::class => 5,
            BazEvent::class => 4,
        ]);

        // There are 6 DB::table calls total:
        // getTotalEventsCount: 2 tables -> 2 calls
        // getEventsInWindow: 2 tables -> 2 calls
        // getEventsPerTypeBreakdown: 2 tables -> 2 calls
        DB::shouldReceive('table')->with('stored_events')->once()->ordered()->andReturn($totalQuery1);
        DB::shouldReceive('table')->with('stored_events_extra')->once()->ordered()->andReturn($totalQuery2);

        DB::shouldReceive('table')->with('stored_events')->once()->ordered()->andReturn($windowQuery1);
        DB::shouldReceive('table')->with('stored_events_extra')->once()->ordered()->andReturn($windowQuery2);

        DB::shouldReceive('table')->with('stored_events')->once()->ordered()->andReturn($breakdownQuery1);
        DB::shouldReceive('table')->with('stored_events_extra')->once()->ordered()->andReturn($breakdownQuery2);

        $result = (new EventSourcingCollector())->collect();

        $this->assertSame(5, $result['events_count']);
        $this->assertSame(['3600s' => 3], $result['events_window_count']);
        $this->assertSame(['FooEvent' => 7, 'BarEvent' => 1, 'BazEvent' => 4], $result['events_per_type_total']);
    }

    public function test_collect_returns_empty_array_when_per_type_breakdown_disabled(): void
    {
        if (!class_exists(EventSourcingServiceProvider::class)) {
            $this->markTestSkipped('spatie/laravel-event-sourcing is not installed in this test runtime.');
        }

        Config::set('prometheus-metrics.collectors.config.event_sourcing.include_per_type_breakdown', false);
        Config::set('prometheus-metrics.collectors.config.event_sourcing.extra_stored_event_tables', []);

        $schema = Mockery::mock(Builder::class);
        $schema->shouldReceive('hasTable')->with('stored_events')->andReturn(true);
        DB::shouldReceive('getSchemaBuilder')->andReturn($schema);

        $totalQuery = Mockery::mock();
        $totalQuery->shouldReceive('count')->once()->andReturn(2);

        $windowQuery = Mockery::mock();
        $windowQuery->shouldReceive('where')
                    ->with('created_at', '>=', Mockery::type(Carbon::class))
                    ->once()
                    ->andReturnSelf();
        $windowQuery->shouldReceive('count')->once()->andReturn(1);

        DB::shouldReceive('table')->with('stored_events')->once()->ordered()->andReturn($totalQuery);
        DB::shouldReceive('table')->with('stored_events')->once()->ordered()->andReturn($windowQuery);

        $result = (new EventSourcingCollector())->collect();

        $this->assertSame(2, $result['events_count']);
        $this->assertSame(['3600s' => 1], $result['events_window_count']);
        $this->assertEquals(new stdClass(), $result['events_per_type_total']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('prometheus-metrics.collectors.config.event_sourcing.enabled', true);
        Config::set('prometheus-metrics.collectors.config.event_sourcing.window_minutes', 60);
        Config::set('prometheus-metrics.collectors.config.event_sourcing.extra_stored_event_tables', []);
        Config::set('prometheus-metrics.collectors.config.event_sourcing.include_per_type_breakdown', true);
    }
}
