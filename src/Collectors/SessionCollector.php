<?php

namespace Faktly\LaravelPrometheusMetrics\Collectors;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SessionCollector extends BaseCollector
{
    public function collect(): array
    {
        if (!$this->isEnabled('session')) {
            return [];
        }

        try {
            $driver = config('session.driver', 'file');
            $activeCount = $this->getActiveSessionCount($driver);

            return [
                'active_count' => $activeCount,
                'driver'       => $driver,
            ];
        } catch (Throwable $e) {
            $this->handleException('SessionCollector', $e);

            return [
                'active_count' => 0,
                'driver'       => config('session.driver', 'file'),
                'error'        => $e->getMessage(),
            ];
        }
    }

    protected function isEnabled(string $feature): bool
    {
        return (bool)config('prometheus-metrics.collectors.config.session.enabled', true);
    }

    private function getActiveSessionCount(string $driver): int
    {
        try {
            return match ($driver) {
                'database' => $this->getDatabaseSessionCount(),
                'redis' => $this->getRedisSessionCount(),
                'memcached' => $this->getMemcachedSessionCount(),
                'file' => $this->getFileSessionCount(),
                // Drivers that cannot be enumerated centrally.
                'array', 'cookie' => 0,
                default => 0,
            };
        } catch (Throwable $e) {
            $this->handleException('getActiveSessionCount', $e);

            return 0;
        }
    }

    private function getDatabaseSessionCount(): int
    {
        try {
            $table = config('session.table', 'sessions');

            if (!Schema::hasTable($table)) {
                return 0;
            }

            $lifetimeMinutes = (int)config('session.lifetime', 120);
            $cutoff = Carbon::now()->subMinutes($lifetimeMinutes)->timestamp;

            return (int)DB::table($table)
                          ->where('last_activity', '>=', $cutoff)
                          ->count();
        } catch (Throwable $e) {
            $this->handleException('getDatabaseSessionCount', $e);

            return 0;
        }
    }

    private function getRedisSessionCount(): int
    {
        try {
            $redis = Redis::connection(config('session.connection', 'default'));
            $prefix = (string)config('session.prefix', 'LARAVEL_SESSION:');

            // Avoid KEYS in production; use SCAN.
            $count = 0;
            $cursor = '0';
            $pattern = $prefix . '*';

            do {
                $result = $redis->scan($cursor, ['match' => $pattern, 'count' => 1000]);

                // Predis returns [cursor, keys]; PhpRedis can return false on failure.
                if ($result === false) {
                    break;
                }

                [$cursor, $keys] = $result;
                $count += is_array($keys) ? count($keys) : 0;
            } while ($cursor !== '0');

            return $count;
        } catch (Throwable $e) {
            $this->handleException('getRedisSessionCount', $e);

            return 0;
        }
    }

    private function getMemcachedSessionCount(): int
    {
        try {
            // Not enumerable reliably without custom instrumentation
            return 0;
        } catch (Throwable $e) {
            $this->handleException('getMemcachedSessionCount', $e);

            return 0;
        }
    }

    private function getFileSessionCount(): int
    {
        try {
            $sessionPath = storage_path('framework/sessions');

            if (!is_dir($sessionPath)) {
                return 0;
            }

            $lifetime = (int)config('session.lifetime', 120);
            $cutoffTime = Carbon::now()->subMinutes($lifetime)->timestamp;
            $count = 0;

            foreach (glob($sessionPath . '/*') as $file) {
                if (is_file($file) && filemtime($file) >= $cutoffTime) {
                    $count++;
                }
            }

            return $count;
        } catch (Throwable $e) {
            $this->handleException('getFileSessionCount', $e);

            return 0;
        }
    }

    public function getName(): string
    {
        return 'session';
    }
}
