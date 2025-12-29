<?php

namespace Faktly\LaravelPrometheusMetrics\Tests;

use Faktly\LaravelPrometheusMetrics\Http\Controllers\MetricsController;
use Faktly\LaravelPrometheusMetrics\Http\Middleware\InternalTokenMiddleware;
use Faktly\LaravelPrometheusMetrics\LaravelPrometheusMetricsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelPrometheusMetricsServiceProvider::class,
        ];
    }

    protected function getApplicationProviders($app)
    {
        return array_merge(
            parent::getApplicationProviders($app),
            [
                LaravelPrometheusMetricsServiceProvider::class,
            ]
        );
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('app.debug', true);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('output.format', 'json');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineRoutes($router): void
    {
        $router->middleware([InternalTokenMiddleware::class])
               ->get('/internal/metrics', MetricsController::class)
               ->name('prometheus.metrics');
    }
}
