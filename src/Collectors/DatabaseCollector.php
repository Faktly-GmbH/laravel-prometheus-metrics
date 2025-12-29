<?php

namespace Faktly\LaravelPrometheusMetrics\Collectors;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseCollector extends BaseCollector
{
    public function collect(): array
    {
        if (!$this->isEnabled('database')) {
            return [];
        }

        return $this->cacheMetrics('database', function () {
            $metrics = [
                'default_connection' => config('database.default'),
                'connections'        => $this->getConnectionsMetrics(),
            ];

            if (config('prometheus-metrics.collectors.config.database.include_query_count', true)
                && config('app.debug')
            ) {
                $metrics['query_count'] = $this->getPerRequestQueryCount();
            }

            return $metrics;
        });
    }

    /**
     * Per-connection metrics based on config plus optional live active count.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getConnectionsMetrics(): array
    {
        $default = config('database.default');
        $connections = config('database.connections', []);

        $includeActive = (bool)config(
            'prometheus-metrics.collectors.config.database.include_active_connections',
            true
        );

        $result = [];

        foreach ($connections as $name => $config) {
            $driver = Arr::get($config, 'driver', 'unknown');
            $database = Arr::get($config, 'database');
            $host = Arr::get($config, 'host');
            $port = Arr::get($config, 'port');
            $max = Arr::get($config, 'max_connections', 100);

            $entry = [
                'driver'     => $driver,
                'host'       => $host,
                'port'       => $port,
                'database'   => $database,
                'is_default' => $name === $default,
                'max'        => $max,
            ];

            if ($includeActive && $database && in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
                $entry['active'] = $this->getActiveConnectionCount($name, $driver, $database);
            }

            $result[$name] = $entry;
        }

        return $result;
    }

    /**
     * Active connections for a specific connection (MySQL / PostgreSQL).
     */
    protected function getActiveConnectionCount(string $connection, string $driver, string $database): int
    {
        try {
            if (in_array($driver, ['mariadb', 'mysql'])) {
                $result = DB::connection($connection)->selectOne(
                    'SELECT COUNT(*) AS count FROM information_schema.processlist WHERE db = ?',
                    [$database]
                );

                return (int)($result?->count ?? 0);
            }

            if ($driver === 'pgsql') {
                $result = DB::connection($connection)->selectOne(
                    'SELECT COUNT(*) AS count FROM pg_stat_activity WHERE datname = ?',
                    [$database]
                );

                return (int)($result?->count ?? 0);
            }
        } catch (Throwable) {
            // Fail silently
        }

        return 0;
    }

    /**
     * Per-request query count (default connection only).
     */
    protected function getPerRequestQueryCount(): int
    {
        try {
            $log = DB::getQueryLog();

            return is_array($log) ? count($log) : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    public function getName(): string
    {
        return 'database';
    }
}
