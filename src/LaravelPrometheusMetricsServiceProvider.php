<?php

namespace Faktly\LaravelPrometheusMetrics;

use Faktly\LaravelPrometheusMetrics\Console\Commands\CleanupPrometheusUserSessionsCommand;
use Faktly\LaravelPrometheusMetrics\Console\Commands\TestMetricsCommand;
use Faktly\LaravelPrometheusMetrics\Contracts\Mail\MetricsStore;
use Faktly\LaravelPrometheusMetrics\Http\Middleware\RecordHttpMetricsMiddleware;
use Faktly\LaravelPrometheusMetrics\Http\Middleware\TrackPrometheusUserSession;
use Faktly\LaravelPrometheusMetrics\Metrics\Cache\CacheMetricsStore as CacheMetricsStore;
use Faktly\LaravelPrometheusMetrics\Metrics\Mail\CacheMetricsStore as MailMetricsStore;
use Faktly\LaravelPrometheusMetrics\Metrics\Mail\MetricsSubscriber as MailMetricsSubscriber;
use Faktly\LaravelPrometheusMetrics\Metrics\Request\CacheMetricsStore as RequestMetricsStore;
use Faktly\LaravelPrometheusMetrics\Support\InstrumentedCacheManager;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class LaravelPrometheusMetricsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/prometheus-metrics.php',
            'prometheus-metrics'
        );

        $this->app->singleton(MetricsAggregator::class, function (Container $container) {
            return new MetricsAggregator($container);
        });

        $this->app->singleton(MetricsStore::class, function ($app) {
            return new MailMetricsStore($app['cache.store']);
        });

        $this->app->singleton(RequestMetricsStore::class, function ($app) {
            return new RequestMetricsStore($app['cache.store']);
        });

        $this->app->singleton(CacheMetricsStore::class);

        if (config('prometheus-metrics.collectors.config.cache.track_operations', false)) {
            $this->app->extend('cache', function ($service, Application $app) {
                // Replace Laravel's CacheManager
                $manager = new InstrumentedCacheManager($app);

                $manager->setMetricsStore($app->make(CacheMetricsStore::class));

                return $manager;
            });
        }
    }

    public function boot(Dispatcher $dispatcher): void
    {
        $this->publishes([
            __DIR__ . '/../config/prometheus-metrics.php' => config_path('prometheus-metrics.php'),
        ], 'prometheus-metrics-config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'prometheus-metrics-migrations');

        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                TestMetricsCommand::class,
                CleanupPrometheusUserSessionsCommand::class,
            ]);
        }

        if (config('prometheus-metrics.collectors.config.mail.track_runtime', false)) {
            $dispatcher->subscribe(MailMetricsSubscriber::class);
        }

        if (config('prometheus-metrics.collectors.config.http.enabled', true)) {
            $this->app['router']->aliasMiddleware('record-http-metrics', RecordHttpMetricsMiddleware::class);
            $this->app['router']->aliasMiddleware('track-user-sessions', TrackPrometheusUserSession::class);
            $this->app['router']->pushMiddlewareToGroup('web', RecordHttpMetricsMiddleware::class);
            $this->app['router']->pushMiddlewareToGroup('api', RecordHttpMetricsMiddleware::class);
            $this->app['router']->pushMiddlewareToGroup('web', TrackPrometheusUserSession::class);
            $this->app['router']->pushMiddlewareToGroup('api', TrackPrometheusUserSession::class);
        }
    }
}
