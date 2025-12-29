<?php

namespace Faktly\LaravelPrometheusMetrics\Tests\Unit\Collectors;

use Faktly\LaravelPrometheusMetrics\Collectors\MailCollector;
use Faktly\LaravelPrometheusMetrics\Contracts\Mail\MetricsStore;
use Faktly\LaravelPrometheusMetrics\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Mockery;
use stdClass;

class MailCollectorTest extends TestCase
{
    public function test_returns_empty_array_when_disabled(): void
    {
        Config::set('prometheus-metrics.collectors.config.mail.enabled', false);

        $result = $this->createCollector()->collect();

        $this->assertSame([], $result);
    }

    private function createCollector(): MailCollector
    {
        return new MailCollector();
    }

    public function test_collect_returns_config_metrics_for_smtp_default(): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers', [
            'smtp' => [
                'transport'  => 'smtp',
                'host'       => 'smtp.example.com',
                'port'       => 587,
                'encryption' => 'tls',
                'username'   => 'user',
            ],
        ]);

        $result = $this->createCollector()->collect();

        $this->assertSame('smtp', $result['default_mailer']);
        $this->assertSame('smtp', $result['default_transport']);
        $this->assertFalse($result['supports_failover']);
        $this->assertSame([], $result['failover_mailers']);

        $this->assertIsArray($result['mailers']);
        $this->assertArrayHasKey('smtp', $result['mailers']);
        $this->assertSame('smtp', $result['mailers']['smtp']['transport']);

        $this->assertEquals(new stdClass(), $result['counters']);
    }

    public function test_collect_returns_failover_details_when_default_is_failover(): void
    {
        Config::set('mail.default', 'failover');
        Config::set('mail.mailers', [
            'failover' => [
                'transport' => 'failover',
                'mailers'   => ['smtp', 'log'],
            ],
            'smtp'     => [
                'transport'  => 'smtp',
                'host'       => 'smtp.example.com',
                'port'       => 587,
                'encryption' => 'tls',
                'username'   => 'user',
            ],
            'log'      => [
                'transport' => 'log',
            ],
        ]);

        $result = $this->createCollector()->collect();

        $this->assertSame('failover', $result['default_mailer']);
        $this->assertSame('failover', $result['default_transport']);
        $this->assertTrue($result['supports_failover']);
        $this->assertSame(['smtp', 'log'], $result['failover_mailers']);

        $this->assertIsArray($result['mailers']);
        $this->assertSame(true, $result['mailers']['failover']['is_failover']);
        $this->assertSame(['smtp', 'log'], $result['mailers']['failover']['failover_mailers']);

        $this->assertEquals(new stdClass(), $result['counters']);
    }

    public function test_collect_returns_runtime_counters_when_enabled(): void
    {
        Config::set('prometheus-metrics.collectors.config.mail.track_runtime', true);

        $store = Mockery::mock(MetricsStore::class);
        $store->shouldReceive('getSendingTotal')->once()->andReturn(10);
        $store->shouldReceive('getSentTotal')->once()->andReturn(8);
        $store->shouldReceive('getFailedTotal')->once()->andReturn(2);

        $this->app->instance(MetricsStore::class, $store);

        $result = $this->createCollector()->collect();

        $this->assertIsArray($result['counters']);
        $this->assertSame(10, $result['counters']['sending_total']);
        $this->assertSame(8, $result['counters']['sent_total']);
        $this->assertSame(2, $result['counters']['failed_total']);
    }

    public function test_collect_returns_empty_mailers_object_when_no_mailers_configured(): void
    {
        Config::set('mail.mailers', []);

        $result = $this->createCollector()->collect();

        $this->assertEquals(new stdClass(), $result['mailers']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('prometheus-metrics.collectors.config.mail.enabled', true);
        Config::set('prometheus-metrics.collectors.config.mail.track_runtime', false);

        // sensible defaults (overridden per test)
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers', [
            'smtp' => [
                'transport'  => 'smtp',
                'host'       => 'smtp.example.com',
                'port'       => 587,
                'encryption' => 'tls',
                'username'   => 'user',
            ],
        ]);
    }
}
