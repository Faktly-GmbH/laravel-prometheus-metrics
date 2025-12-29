<?php

namespace Faktly\LaravelPrometheusMetrics\Collectors;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;
use Throwable;

class QueueCollector extends BaseCollector
{
    public function collect(): array
    {
        if (!$this->isEnabled('queue')) {
            return [];
        }

        try {
            $driver = config('queue.default', 'sync');

            if ($driver !== 'database') {
                return [
                    'pending_count' => 0,
                    'failed_count'  => 0,
                    'driver'        => $driver,
                ];
            }

            $pendingCount = $this->getPendingJobsCount();
            $failedCount = $this->getFailedJobsCount();
            $perQueueBreakdown = config('prometheus-metrics.collectors.config.queue.include_per_queue_breakdown', true)
                ? $this->getPerQueueBreakdown()
                : [];

            return array_merge(
                [
                    'pending_count' => $pendingCount,
                    'failed_count'  => $failedCount,
                    'driver'        => $driver,
                ],
                $perQueueBreakdown
            );
        } catch (Throwable $e) {
            $this->handleException('QueueCollector', $e);

            return [
                'pending_count' => 0,
                'failed_count'  => 0,
                'driver'        => config('queue.default', 'sync'),
                'error'         => $e->getMessage(),
            ];
        }
    }

    protected function isEnabled(string $feature): bool
    {
        return config('prometheus-metrics.collectors.config.queue.enabled', true);
    }

    private function getPendingJobsCount(): int
    {
        try {
            $table = config('queue.connections.database.table', 'jobs');

            if (!$this->tableExists($table)) {
                return 0;
            }

            return DB::table($table)
                     ->where('reserved_at', null)
                     ->count();
        } catch (Throwable $e) {
            $this->handleException('getPendingJobsCount', $e);

            return 0;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable $e) {
            $this->handleException('tableExists', $e);

            return false;
        }
    }

    private function getFailedJobsCount(): int
    {
        try {
            $table = config('queue.failed.table', 'failed_jobs');

            if (!$this->tableExists($table)) {
                return 0;
            }

            return DB::table($table)->count();
        } catch (Throwable $e) {
            $this->handleException('getFailedJobsCount', $e);

            return 0;
        }
    }

    private function getPerQueueBreakdown(): array
    {
        try {
            $table = config('queue.connections.database.table', 'jobs');

            if (!$this->tableExists($table)) {
                return [];
            }

            $breakdown = DB::table($table)
                           ->where('reserved_at', null)
                           ->select('queue', DB::raw('COUNT(*) as count'))
                           ->groupBy('queue')
                           ->get()
                           ->pluck('count', 'queue')
                           ->toArray();

            return [
                'pending_per_queue' => $breakdown ?: new stdClass(),
            ];
        } catch (Throwable $e) {
            $this->handleException('getPerQueueBreakdown', $e);

            return [];
        }
    }

    public function getName(): string
    {
        return 'queue';
    }
}
