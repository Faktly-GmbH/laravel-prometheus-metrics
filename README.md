# Laravel Prometheus Metrics

[![Tests](https://github.com/faktly/laravel-prometheus-metrics/workflows/Tests/badge.svg)](https://github.com/faktly/laravel-prometheus-metrics/actions?query=workflow:Tests)
[![Publish Release](https://github.com/faktly/laravel-prometheus-metrics/workflows/Publish%20Release/badge.svg)](https://github.com/faktly/laravel-prometheus-metrics/releases)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/faktly/laravel-prometheus-metrics.svg?style=flat-square)](https://packagist.org/packages/faktly/laravel-prometheus-metrics)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

A comprehensive metrics exporter for Laravel that provides application insights via JSON for Prometheus, Grafana, and external monitoring tools. Collects 10+ different metrics from your Laravel application with zero configuration needed.

## Features

✨ **10+ Metric Collectors** - Database, Sessions, Queue, Mail, Cache, Users, Event Sourcing, Permissions, Horizon, Meilisearch

🔌 **Fully Optional** - Each collector can be disabled independently

🚀 **High Performance** - Built-in caching with configurable TTL

🔒 **Secure** - Token-based authentication with hash-equals comparison

📦 **Framework Compatible** - Laravel 11+, PHP 8.2+

🛠️ **Configurable** - 30+ configuration options via environment variables

📊 **JSON Export** - Ready for Prometheus/Grafana or custom exporters

🧪 **Well Tested** - 30+ test methods covering all collectors

## Quick Start

### Installation

```bash
composer require faktly/laravel-prometheus-metrics
php artisan vendor:publish --provider="Faktly\LaravelPrometheusMetrics\LaravelPrometheusMetricsServiceProvider"
```

#### Count user session with prometheus_metrics_user_sessions table

In some cases, you might use array/cookie session driver. Those session can not be counted. Therefore the metrics would 
be messing. Therefore, we provide a database table and middleware, which still counts the user sessions – no matter 
which driver is being used.

If you use UUID/ULID, you might want to adjust the published database migration before you apply them.  

### Configure

```env
PROMETHEUS_METRICS_TOKEN=your-secret-token-here
```

#### Configure Prometheus scrape config

You want your locally installed prometheus instance to scrape the laravel metrics. This can be accomplished by adding
the following **scrape_config** to `/etc/prometheus/prometheus.yml`:

```
    - job_name: "laravel"
      scheme: https
      metrics_path: /internal/metrics
      http_headers:
        X-Metrics-Token:
          values: ["YOUR_TOKEN"]
      static_configs:
        - targets: ["YOUR_HOST:443"]
```

Exchange **YOUR_TOKEN** with a secure token. You can create one with ``openssl rand -hex 64`` and **YOUR_HOST** could be
a local **IP:PORT** combination or your website **HOST:PORT**.

### Access Metrics

```bash
curl -H "X-Metrics-Token: your-secret-token-here" \
  http://localhost/internal/metrics
```

#### Register Middleware for HTTP metrics

In some cases – especially legacy apps or with custom Middleware setup – you might want to register the required 
middleware for HTTP metrics as soon as possible but after Session middleware. Add this to your Kernel.php or in newer 
Laravel versions to bootstrap/app.php:

```php
->withMiddleware(function (Middleware $middleware) {
    // Global HTTP Middleware - runs for every request
    $middleware->use([
        \Faktly\LaravelPrometheusMetrics\Http\Middleware\RecordHttpMetricsMiddleware::class,
        \Faktly\LaravelPrometheusMetrics\Http\Middleware\TrackPrometheusUserSession::class,
    ]);
     // Middleware Priority
    $middleware->priority([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Faktly\LaravelPrometheusMetrics\Http\Middleware\RecordHttpMetricsMiddleware::class,
        \Faktly\LaravelPrometheusMetrics\Http\Middleware\TrackPrometheusUserSession::class,
    ]);
```

### Test Locally

```bash
php artisan prometheus:test-metrics
```

## What Gets Measured

| Collector          | Metrics                            | Optional | Requires         |
|--------------------|------------------------------------|----------|------------------|
| **Database**       | Active connections, queries        | ✅        | Laravel DB       |
| **Sessions**       | Active count, driver info          | ✅        | Laravel Sessions |
| **Queue**          | Pending/failed jobs, per-queue     | ✅        | Laravel Queue    |
| **Mail**           | Sent/failed count                  | ✅        | Laravel Mail     |
| **Cache**          | Driver info, hits/misses           | ✅        | Laravel Cache    |
| **Users**          | Total count, active, per-role      | ✅        | Laravel Auth     |
| **Event Sourcing** | Events total, aggregates, per-type | ✅        | Spatie           |
| **Permissions**    | Roles, permissions, users per role | ✅        | Spatie           |
| **Horizon**        | Jobs per minute, processes         | ✅        | Laravel          |
| **Meilisearch**    | Health, indexes, documents         | ✅        | Meilisearch      |

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.
