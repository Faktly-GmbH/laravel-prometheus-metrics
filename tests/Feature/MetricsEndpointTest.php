<?php

namespace Faktly\LaravelPrometheusMetrics\Tests\Feature;

use Faktly\LaravelPrometheusMetrics\Tests\TestCase;
use Illuminate\Support\Str;

class MetricsEndpointTest extends TestCase
{
    public function test_metrics_endpoint_requires_token(): void
    {
        config()->set('prometheus-metrics.auth.enabled', true);
        config()->set('prometheus-metrics.auth.token', 'invalid-secret-token');

        $response = $this->getJson('/internal/metrics');

        $response->assertStatus(401);
    }

    public function test_metrics_endpoint_are_disabled(): void
    {
        config()->set('prometheus-metrics.enabled', false);
        config()->set('prometheus-metrics.auth.enabled', false);

        $response = $this->getJson('/internal/metrics');

        $response->assertJsonStructure([
            'error',
        ]);
        $response->assertStatus(418);
    }

    public function test_metrics_endpoint_returns_json_when_authenticated(): void
    {
        config()->set('prometheus-metrics.auth.enabled', true);
        config()->set('prometheus-metrics.auth.token', 'secret-token');

        $response = $this->getJson('/internal/metrics', [
            'X-Metrics-Token' => 'secret-token',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');

        $response->assertJsonStructure([
            'timestamp',
            'metadata' => ['app_name', 'app_env'],
        ]);
    }

    public function test_metrics_endpoint_fails_when_authentication_is_not_configured(): void
    {
        config()->set('prometheus-metrics.auth.enabled', true);

        $response = $this->getJson('/internal/metrics');

        $response->assertStatus(500);
        $this->assertTrue(Str::contains($response->content(), 'Authentication not configured'));
    }

    public function test_metrics_endpoint_returns_prometheus_format(): void
    {
        config()->set('prometheus-metrics.auth.enabled', false);
        config()->set('prometheus-metrics.output.format', 'prometheus');

        $this->get('/');

        $response = $this->get('/internal/metrics', [
            'Accept' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');

        $content = $response->getContent();

        // Check for actual Prometheus metrics (without HELP/TYPE comments)
        $this->assertStringContainsString('laravel_', $content);

        // Check for gauge metrics
        $this->assertStringContainsString('laravel_cache_operations_hits', $content);
        $this->assertStringContainsString('laravel_user_count', $content);

        // Check for histogram buckets if HTTP metrics are present
        if (str_contains($content, 'laravel_http_request_duration')) {
            $this->assertStringContainsString('le="+Inf"', $content);
            $this->assertStringContainsString('_bucket{', $content);
            $this->assertStringContainsString('_count{', $content);
            $this->assertStringContainsString('_sum{', $content);
        }
    }

    public function test_metrics_endpoint_returns_yaml_format(): void
    {
        config()->set('prometheus-metrics.auth.enabled', false);
        config()->set('prometheus-metrics.output.format', 'yaml');

        $this->get('/');

        $response = $this->get('/internal/metrics', [
            'Accept' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');

        $content = $response->getContent();

        // Check for YAML structure - top level keys
        $this->assertStringContainsString('timestamp:', $content);
        $this->assertStringContainsString('metadata:', $content);
        $this->assertStringContainsString('app_name:', $content);
        $this->assertStringContainsString('app_env:', $content);

        // Check for metrics sections
        $this->assertStringContainsString('http:', $content);
        $this->assertStringContainsString('cache:', $content);
        $this->assertStringContainsString('database:', $content);

        // Check YAML indentation and structure
        $this->assertStringContainsString('  ', $content); // At least 2-space indentation
        $this->assertStringMatchesFormat('%a:%a', $content); // Key: value format
    }

    public function test_metrics_endpoint_returns_partial_json_when_collectors_are_disabled(): void
    {
        config()->set('prometheus-metrics.auth.enabled', true);
        config()->set('prometheus-metrics.auth.token', 'secret-token');
        config()->set('prometheus-metrics.collectors.config.http.enabled', false);

        $response = $this->getJson('/internal/metrics', [
            'X-Metrics-Token' => 'secret-token',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');

        $response->assertJsonStructure([
            'timestamp',
            'metadata' => ['app_name', 'app_env'],
        ]);

        $this->assertFalse(Str::contains($response->content(), '"http":'));
    }

    public function test_http_metrics_endpoint_returns_data(): void
    {
        config()->set('prometheus-metrics.auth.enabled', false);

        $response = $this->get('/');
        $this->assertTrue($response->status() < 500);

        $response = $this->getJson('/internal/metrics');

        if ($response->status() === 500) {
            dump('Status: ' . $response->status());
            dump('Response: ' . $response->content());
            dump('JSON: ', $response->json());
        }

        $response->assertOk();

        $metrics = $response->json();

        $this->assertArrayHasKey('http', $metrics);
        $this->assertArrayHasKey('requests_total', $metrics['http']);
    }
}
