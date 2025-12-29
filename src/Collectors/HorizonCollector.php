<?php

namespace Faktly\LaravelPrometheusMetrics\Collectors;

use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Horizon;
use stdClass;
use Throwable;

class HorizonCollector extends BaseCollector
{
    public function getName(): string
    {
        return 'horizon';
    }

    public function collect(): array
    {
        if (!$this->isEnabled('horizon')) {
            return [];
        }

        if (!$this->isPackageInstalled()) {
            return [
                'enabled'             => false,
                // Horizon provides rolling 1m rate (jobs_per_minute)
                'jobs_per_minute'     => 0.0,
                'processed_total'     => 0,
                'processed_per_queue' => new stdClass(),
            ];
        }

        try {
            $jobsPerMinute = $this->getJobsPerMinute();
            $processesTotal = $this->getProcessedTotal();

            $processesPerQueue = config(
                'prometheus-metrics.collectors.config.horizon.include_processed_per_queue',
                true
            )
                ? $this->getProcessesPerQueue()
                : ['processed_per_queue' => new stdClass()];

            return [
                'enabled'             => true,
                'jobs_per_minute'     => $jobsPerMinute,
                'processed_total'     => $processesTotal,
                'processed_per_queue' => $processesPerQueue['processed_per_queue'] ?? new stdClass(),
            ];
        } catch (Throwable $e) {
            $this->handleException('HorizonCollector', $e);

            return [
                'enabled'             => true,
                'jobs_per_minute'     => 0.0,
                'processed_total'     => 0,
                'processed_per_queue' => new stdClass(),
                'error'               => $e->getMessage(),
            ];
        }
    }

    protected function isEnabled(string $feature): bool
    {
        return config('prometheus-metrics.collectors.config.horizon.enabled', true);
    }

    private function isPackageInstalled(): bool
    {
        return class_exists(Horizon::class);
    }

    private function getJobsPerMinute(): float
    {
        try {
            $connection = config('horizon.redis_connection', 'default');
            $prefix = rtrim((string)config('horizon.prefix', 'horizon'), ':');

            $redis = Redis::connection($connection);

            $metrics = $redis->get("{$prefix}:metrics:jobs");

            if (!$metrics) {
                return 0.0;
            }

            $data = json_decode($metrics, true) ?: [];

            return (float)($data['jobs_per_minute'] ?? 0);
        } catch (Throwable $e) {
            $this->handleException('getJobsPerMinute', $e);

            return 0.0;
        }
    }

    private function getProcessedTotal(): int
    {
        try {
            $connection = config('horizon.redis_connection', 'default');
            $prefix = rtrim((string)config('horizon.prefix', 'horizon'), ':');

            $redis = Redis::connection($connection);

            $keys = $redis->keys("{$prefix}:master:*:processes");

            if (empty($keys)) {
                return 0;
            }

            $total = 0;

            foreach ($keys as $key) {
                $processes = $redis->hgetall($key);
                $total += count($processes);
            }

            return $total;
        } catch (Throwable $e) {
            $this->handleException('getProcessedTotal', $e);

            return 0;
        }
    }

    private function getProcessesPerQueue(): array
    {
        try {
            $connection = config('horizon.redis_connection', 'default');
            $prefix = rtrim((string)config('horizon.prefix', 'horizon'), ':');

            $redis = Redis::connection($connection);

            $keys = $redis->keys("{$prefix}:master:*:processes");

            if (empty($keys)) {
                return ['processed_per_queue' => new stdClass()];
            }

            $breakdown = [];

            foreach ($keys as $masterKey) {
                $processes = $redis->hgetall($masterKey);

                foreach ($processes as $processData) {
                    $data = json_decode($processData, true) ?: [];
                    $queue = $data['queue'] ?? 'default';

                    if (!isset($breakdown[$queue])) {
                        $breakdown[$queue] = 0;
                    }

                    $breakdown[$queue]++;
                }
            }

            return [
                'processed_per_queue' => $breakdown ?: new stdClass(),
            ];
        } catch (Throwable $e) {
            $this->handleException('getProcessesPerQueue', $e);

            return ['processed_per_queue' => new stdClass()];
        }
    }
}
