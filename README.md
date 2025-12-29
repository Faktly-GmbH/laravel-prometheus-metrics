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
```

### Configure

```env
PROMETHEUS_METRICS_TOKEN=your-secret-token-here
```

### Access Metrics

```bash
curl -H "X-Metrics-Token: your-secret-token-here" \
  http://localhost/internal/metrics
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
