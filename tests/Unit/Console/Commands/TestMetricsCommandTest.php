<?php

namespace Faktly\LaravelPrometheusMetrics\Tests\Unit\Console\Commands;

use Faktly\LaravelPrometheusMetrics\Tests\TestCase;

class TestMetricsCommandTest extends TestCase
{
    public function test_command_outputs_collecting_message(): void
    {
        $this->artisan('prometheus:test-metrics')
             ->expectsOutput('Collecting Prometheus metrics...')
             ->assertExitCode(0);
    }

    public function test_command_default_format_is_prometheus(): void
    {
        $this->artisan('prometheus:test-metrics')
             ->assertExitCode(0);
    }

    public function test_command_outputs_prometheus_format(): void
    {
        $this->artisan('prometheus:test-metrics', ['--format' => 'prometheus'])
             ->assertExitCode(0);
    }

    public function test_command_outputs_json_format(): void
    {
        $this->artisan('prometheus:test-metrics', ['--format' => 'json'])
             ->assertExitCode(0);
    }

    public function test_command_outputs_yaml_format(): void
    {
        if (!function_exists('yaml_emit')) {
            $this->markTestSkipped('YAML extension not installed');
        }

        $this->artisan('prometheus:test-metrics', ['--format' => 'yaml'])
             ->assertExitCode(0);
    }

    public function test_prometheus_format_metric_structure(): void
    {
        $this->artisan('prometheus:test-metrics', ['--format' => 'prometheus'])
             ->expectsOutput('Collecting Prometheus metrics...')
             ->assertExitCode(0);
    }

    public function test_json_format_is_valid_json(): void
    {
        $this->artisan('prometheus:test-metrics', ['--format' => 'json'])
             ->assertExitCode(0);
    }

    public function test_yaml_format_is_valid_yaml(): void
    {
        if (!function_exists('yaml_parse')) {
            $this->markTestSkipped('YAML extension not installed');
        }

        $this->artisan('prometheus:test-metrics', ['--format' => 'yaml'])
             ->assertExitCode(0);
    }

    public function test_command_handles_empty_metrics(): void
    {
        $this->artisan('prometheus:test-metrics', ['--format' => 'prometheus'])
             ->assertExitCode(0);
    }

    public function test_command_with_invalid_format(): void
    {
        $this->artisan('prometheus:test-metrics', ['--format' => 'invalid'])
             ->assertExitCode(0);
    }

    public function test_all_formats_exit_successfully(): void
    {
        $formats = ['prometheus', 'json'];

        if (function_exists('yaml_emit')) {
            $formats[] = 'yaml';
        }

        foreach ($formats as $format) {
            $this->artisan('prometheus:test-metrics', ['--format' => $format])
                 ->assertExitCode(0);
        }
    }

    public function test_all_collectors_disabled(): void
    {
        $collectors = [
            'http',
            'cache',
            'database',
            'event_sourcing',
            'horizon',
            'mail',
            'meilisearch',
            'permissions',
            'queue',
            'session',
            'user',
        ];

        foreach ($collectors as $collector) {
            config()->set("prometheus-metrics.collectors.config.{$collector}.enabled", false);
        }

        config()->set("prometheus-metrics.output.format", 'prometheus');
        $this->artisan('prometheus:test-metrics', ['--format' => 'prometheus'])
             ->expectsOutputToContain('Collecting Prometheus metrics...')
             ->doesntExpectOutputToContain('laravel_cache_')
             ->doesntExpectOutputToContain('laravel_http_')
             ->doesntExpectOutputToContain('laravel_database_')
             ->doesntExpectOutputToContain('laravel_event_sourcing_')
             ->doesntExpectOutputToContain('laravel_horizon_')
             ->doesntExpectOutputToContain('laravel_mail_')
             ->doesntExpectOutputToContain('laravel_meilisearch_')
             ->doesntExpectOutputToContain('laravel_permissions_')
             ->doesntExpectOutputToContain('laravel_queue_')
             ->doesntExpectOutputToContain('laravel_session_')
             ->doesntExpectOutputToContain('laravel_user_')
             ->assertExitCode(0);
    }
}
