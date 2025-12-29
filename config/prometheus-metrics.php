<?php

use Faktly\LaravelPrometheusMetrics\Http\Middleware\InternalTokenMiddleware;

return [
    'enabled' => env('PROMETHEUS_METRICS_ENABLED', true),

    'metadata' => [
        'app_name'    => env('APP_NAME', 'Laravel'),
        'app_version' => env('APP_VERSION', '1.0.0'),
        'environment' => env('APP_ENV', 'production'),
    ],

    'auth'     => [
        'enabled' => env('PROMETHEUS_METRICS_AUTH_ENABLED', true),
        'token'   => env('PROMETHEUS_METRICS_TOKEN', null),
    ],

    /**
     * URI to use for metrics exposure.
     */
    'endpoint' => env('PROMETHEUS_METRICS_ENDPOINT', '/internal/metrics'),

    'output' => [
        /**
         * Could be one of: json, yaml or prometheus.
         */
        'format' => env('PROMETHEUS_METRICS_OUTPUT_FORMAT', 'prometheus'),
    ],

    'prometheus' => [
        /**
         * Defines the prefix being used for your resulting prometheus metrics.
         */
        'prefix'     => env('PROMETHEUS_METRICS_PREFIX', 'laravel_'),

        /**
         * Convert a nested map at `path` into labeled samples.
         *
         * Example input shape:
         *   event_sourcing => [ events_count => [ OrderPlaced => 1, ... ] ]
         *
         * Output samples:
         *   prometheus_metrics_event_sourcing_events_count_total{event_type="order_placed"} 1
         */
        'label_maps' => [
            [
                'path'              => 'event_sourcing.events_window_count',
                'metric'            => 'event_sourcing.events_window_count',
                'label'             => 'window',
                'snake_case_values' => false, // E.g. keep "86400s" as-is
            ],
            [
                'path'              => 'event_sourcing.events_per_type_total',
                'metric'            => 'event_sourcing.events_per_type_total',
                'label'             => 'event_type',
                'snake_case_values' => true,
            ],
            [
                'path'              => 'permissions.users_per_role',
                'metric'            => 'permissions.users_per_role',
                'label'             => 'role',
                'snake_case_values' => true,
            ],
            [
                'path'              => 'permissions.permissions_per_role',
                'metric'            => 'permissions_per_role',
                'label'             => 'role',
                'snake_case_values' => true,
            ],
            [
                'path'              => 'meilisearch.metrics.documents_per_index',
                'metric'            => 'meilisearch_documents_count',
                'label'             => 'index',
                'snake_case_values' => false,
            ],
            [
                'path'              => 'http.requests_total',
                'metric'            => 'http.requests_total',
                'label'             => 'label', // dummy, we'll handle manually
                'snake_case_values' => false,
            ],
            [
                'path'              => 'http.request_duration_seconds',
                'metric'            => 'http.request_duration_seconds',
                'label'             => 'label',
                'snake_case_values' => false,
            ],
            [
                'path'              => 'http.request_size_bytes',
                'metric'            => 'http.request_size_bytes',
                'label'             => 'label',
                'snake_case_values' => false,
            ],
            [
                'path'              => 'http.response_size_bytes',
                'metric'            => 'http.response_size_bytes',
                'label'             => 'label',
                'snake_case_values' => false,
            ],
        ],
    ],

    'collectors' => [
        /**
         * Can be extended with additional collectors or to overwrite existing
         * collectors.
         */
        'classes' => [
            Faktly\LaravelPrometheusMetrics\Collectors\HTTPCollector::class,
            Faktly\LaravelPrometheusMetrics\Collectors\CacheCollector::class,
            Faktly\LaravelPrometheusMetrics\Collectors\DatabaseCollector::class,
            Faktly\LaravelPrometheusMetrics\Collectors\EventSourcingCollector::class,
            Faktly\LaravelPrometheusMetrics\Collectors\HorizonCollector::class,
            Faktly\LaravelPrometheusMetrics\Collectors\MailCollector::class,
            Faktly\LaravelPrometheusMetrics\Collectors\MeilisearchCollector::class,
            Faktly\LaravelPrometheusMetrics\Collectors\PermissionsCollector::class,
            Faktly\LaravelPrometheusMetrics\Collectors\QueueCollector::class,
            Faktly\LaravelPrometheusMetrics\Collectors\SessionCollector::class,
            Faktly\LaravelPrometheusMetrics\Collectors\UserCollector::class,
        ],

        /**
         * Additional configuration options for each collector.
         */
        'config'  => [
            'database'       => [
                'enabled'                  => env('PROMETHEUS_METRICS_DATABASE', true),
                'include_connection_count' => true,
                'include_query_count'      => true,
            ],
            'session'        => [
                'enabled'      => env('PROMETHEUS_METRICS_SESSIONS', true),
                'count_driver' => true,
            ],
            'cache'          => [
                'enabled'          => env('PROMETHEUS_METRICS_CACHE', true),
                'track_operations' => true,
            ],
            'queue'          => [
                'enabled'                     => env('PROMETHEUS_METRICS_QUEUE', true),
                'include_failed_jobs'         => true,
                'include_per_queue_breakdown' => true,
            ],
            'mail'           => [
                'enabled'       => env('PROMETHEUS_METRICS_MAIL', true),
                'track_runtime' => true,
            ],
            'user'           => [
                'enabled'          => env('PROMETHEUS_METRICS_USERS', true),
                'include_per_role' => true,
            ],
            'event_sourcing' => [
                'enabled'                    => env('PROMETHEUS_METRICS_EVENT_SOURCING', true),
                'include_per_type_breakdown' => true,
                'window_minutes'             => 60 * 24,
                // additional stored event tables or models (you might want to have a different table per aggregate root):
                'extra_stored_event_tables'  => [
                    // 'stored_events_of_transactions',
                    // 'celebration_stored_events',
                ],
                // Named sources for per-source metrics
                'sources'                    => [
                    // 'default' => [
                    //     'table' => 'stored_events',
                    // ],
                    // 'transactions' => [
                    //     'table' => 'stored_events_of_transactions',
                    // ],
                ],
            ],
            'horizon'        => [
                'enabled'                     => env('PROMETHEUS_METRICS_HORIZON', true),
                'include_per_queue'           => true,
                'include_processed_per_queue' => true,
            ],
            'meilisearch'    => [
                'enabled'           => env('PROMETHEUS_METRICS_MEILISEARCH', true),
                'track_index_stats' => true,
            ],
            'permissions'    => [
                'enabled'                => env('PROMETHEUS_METRICS_PERMISSIONS', true),
                'include_role_breakdown' => true,
            ],
            'http'           => [
                'enabled' => env('PROMETHEUS_METRICS_HTTP', true),
            ],
        ],
    ],

    'cache' => [
        'enabled' => env('PROMETHEUS_METRICS_CACHE_ENABLED', true),
        'ttl'     => env('PROMETHEUS_METRICS_CACHE_TTL', 60),
        'prefix'  => 'prometheus_metrics:',
    ],

    'middleware' => [
        'api',
        InternalTokenMiddleware::class,
    ],
];
