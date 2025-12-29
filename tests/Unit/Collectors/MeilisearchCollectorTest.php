<?php

namespace Faktly\LaravelPrometheusMetrics\Tests\Unit\Collectors;

use Faktly\LaravelPrometheusMetrics\Collectors\MeilisearchCollector;
use Faktly\LaravelPrometheusMetrics\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Meilisearch\Client;
use Meilisearch\Contracts\IndexesResults;
use Mockery;
use stdClass;

class MeilisearchCollectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('prometheus-metrics.collectors.config.meilisearch.enabled', true);
        Config::set('prometheus-metrics.collectors.config.meilisearch.track_index_stats', true);
        Config::set('meilisearch.host', 'http://127.0.0.1:7700');
        Config::set('meilisearch.key', null);
    }

    private function createCollector(Client $client): MeilisearchCollector
    {
        return new class ($client) extends MeilisearchCollector {
            public function __construct(private Client $client)
            {
            }

            protected function getClient(): Client
            {
                return $this->client;
            }
        };
    }

    public function test_returns_empty_array_when_disabled(): void
    {
        Config::set('prometheus-metrics.collectors.config.meilisearch.enabled', false);

        $result = (new MeilisearchCollector())->collect();

        $this->assertSame([], $result);
    }

    public function test_returns_up_and_index_stats_when_healthy(): void
    {
        if (! class_exists(Client::class)) {
            $this->markTestSkipped('meilisearch/meilisearch-php is not installed in this test runtime.');
        }

        $client = Mockery::mock(Client::class);

        $client->shouldReceive('health')
               ->once()
               ->andReturn(['status' => 'available', 'database' => 'available']);

        $indexesResults = Mockery::mock(IndexesResults::class);
        $indexesResults->shouldReceive('toArray')->once()->andReturn([
            'results' => [
                ['uid' => 'movies', 'numberOfDocuments' => 10],
                ['uid' => 'books', 'numberOfDocuments' => 5],
            ],
        ]);

        $client->shouldReceive('getIndexes')
               ->once()
               ->andReturn($indexesResults);

        $collector = $this->createCollector($client);

        $result = $collector->collect();

        $this->assertSame(1, $result['up']);
        $this->assertSame(2, $result['indexes_count']);
        $this->assertSame(15, $result['documents_count']);
        $this->assertSame(['movies' => 10, 'books' => 5], $result['documents_per_index']);
    }

    public function test_returns_zero_stats_when_index_stats_disabled(): void
    {
        if (! class_exists(Client::class)) {
            $this->markTestSkipped('meilisearch/meilisearch-php is not installed in this test runtime.');
        }

        Config::set('prometheus-metrics.collectors.config.meilisearch.track_index_stats', false);

        $client = Mockery::mock(Client::class);

        $client->shouldReceive('health')
               ->once()
               ->andReturn(['status' => 'available', 'database' => 'available']);

        // Must NOT be called when stats are disabled
        $client->shouldReceive('getIndexes')->never();

        $collector = $this->createCollector($client);

        $result = $collector->collect();

        $this->assertSame(1, $result['up']);
        $this->assertSame(0, $result['indexes_count']);
        $this->assertSame(0, $result['documents_count']);
        $this->assertEquals(new stdClass(), $result['documents_per_index']);
    }
}
