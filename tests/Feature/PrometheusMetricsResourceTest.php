<?php

namespace Faktly\LaravelPrometheusMetrics\Tests\Feature;

use Faktly\LaravelPrometheusMetrics\Http\Resource\PrometheusMetricsResource;
use Faktly\LaravelPrometheusMetrics\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class PrometheusMetricsResourceTest extends TestCase
{
    public function test_renders_prometheus_text_format_with_label_maps_and_filters_ports(): void
    {
        $metrics = [
            'event_sourcing' => [
                'events_count'               => 3,
                'events_events_window_count' => 0,
                'events_per_type'            => [
                    'OrderPlaced'      => 1,
                    'UserEmailChanged' => 1,
                    'UserRegistered'   => 1,
                ],
            ],

            'database' => [
                'connections' => [
                    'mysql' => [
                        'active' => 2,
                        'max'    => 100,
                        'port'   => 3306, // must be filtered out by _port rule
                    ],
                ],
                'query_count' => 0,
            ],

            'mail' => [
                'counters' => [
                    'sent_total'   => 0,
                    'failed_total' => 0,
                ],
                'mailers'  => [
                    'smtp' => [
                        'port' => 1025, // must be filtered out by _port rule
                    ],
                ],
            ],

            'non_numeric' => [
                'foo' => 'bar', // must not be emitted
            ],
        ];

        $resource = new PrometheusMetricsResource($metrics);
        $response = $resource->toResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/plain; version=0.0.4', $response->headers->get('Content-Type'));

        $body = $response->getContent();

        // scalar metrics
        $this->assertStringContainsString("laravel_event_sourcing_events_count 3\n", $body);
        $this->assertStringContainsString("laravel_event_sourcing_events_events_window_count 0\n", $body);

        $this->assertStringContainsString("laravel_database_connections_mysql_active 2\n", $body);
        $this->assertStringContainsString("laravel_database_connections_mysql_max 100\n", $body);
        $this->assertStringContainsString("laravel_database_query_count 0\n", $body);

        $this->assertStringContainsString("laravel_mail_counters_sent_total 0\n", $body);
        $this->assertStringContainsString("laravel_mail_counters_failed_total 0\n", $body);

        // label map metrics (snake-cased values)
        $this->assertStringContainsString(
            "laravel_event_sourcing_events_per_type_total{event_type=\"order_placed\"} 1\n",
            $body
        );
        $this->assertStringContainsString(
            "laravel_event_sourcing_events_per_type_total{event_type=\"user_email_changed\"} 1\n",
            $body
        );
        $this->assertStringContainsString(
            "laravel_event_sourcing_events_per_type_total{event_type=\"user_registered\"} 1\n",
            $body
        );

        // excluded subtree must not be emitted as separate metrics
        $this->assertStringNotContainsString("laravel_event_sourcing_events_per_type_order_placed", $body);
        $this->assertStringNotContainsString(
            "laravel_event_sourcing_events_per_type_user_email_changed",
            $body
        );
        $this->assertStringNotContainsString(
            "laravel_event_sourcing_events_per_type_user_registered",
            $body
        );

        // _port metrics must be filtered out
        $this->assertStringNotContainsString("laravel_database_connections_mysql_port 3306\n", $body);
        $this->assertStringNotContainsString("laravel_mail_mailers_smtp_port 1025\n", $body);

        // non-numeric must be filtered out
        $this->assertStringNotContainsString("laravel_non_numeric_foo", $body);
    }

    public function test_escapes_label_values_in_text_format(): void
    {
        Config::set('prometheus-metrics.prometheus.label_maps', [
            [
                'path'              => 'event_sourcing.events_per_type',
                'metric'            => 'event_sourcing.events_per_type_total',
                'label'             => 'event_type',
                'snake_case_values' => false,
            ],
        ]);

        $metrics = [
            'event_sourcing' => [
                'events_per_type' => [
                    "Foo\"Bar\nBaz\\Qux" => 1,
                ],
            ],
        ];

        $resource = new PrometheusMetricsResource($metrics);
        $body = $resource->toResponse()->getContent();

        $this->assertStringContainsString(
            "laravel_event_sourcing_events_per_type_total{event_type=\"Foo\\\"Bar\\nBaz\\\\Qux\"} 1\n",
            $body
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('prometheus-metrics.prometheus.prefix', 'laravel_');

        Config::set('prometheus-metrics.prometheus.label_maps', [
            [
                'path'              => 'event_sourcing.events_per_type',
                'metric'            => 'event_sourcing.events_per_type_total',
                'label'             => 'event_type',
                'snake_case_values' => true,
            ],
        ]);
    }
}
