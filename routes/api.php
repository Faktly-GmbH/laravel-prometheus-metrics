<?php

use Faktly\LaravelPrometheusMetrics\Http\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('prometheus-metrics.middleware', []))
    ->get(
        config('prometheus-metrics.endpoint', '/internal/metrics'),
        MetricsController::class
    )
    ->name('prometheus.metrics');
