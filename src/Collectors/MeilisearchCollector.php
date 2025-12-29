<?php

namespace Faktly\LaravelPrometheusMetrics\Collectors;

use Meilisearch\Client;
use stdClass;
use Throwable;

class MeilisearchCollector extends BaseCollector
{
    public function collect(): array
    {
        if (!$this->isEnabled('meilisearch')) {
            return [];
        }

        if (!$this->isPackageInstalled()) {
            return [
                'up'                  => 0,
                'indexes_count'       => 0,
                'documents_count'     => 0,
                'documents_per_index' => new stdClass(),
            ];
        }

        try {
            $health = $this->getHealth();
            $metrics = [
                'up' => ($health['status'] ?? null) === 'available' ? 1 : 0,
            ];

            if (config('prometheus-metrics.collectors.config.meilisearch.track_index_stats', true)) {
                $stats = $this->getIndexStatistics();
                $metrics = array_merge($metrics, [
                    'indexes_count'       => $stats['indexes_count'],
                    'documents_count'     => $stats['documents_count'],
                    'documents_per_index' => $stats['documents_per_index'],
                ]);
            } else {
                $metrics = array_merge($metrics, [
                    'indexes_count'       => 0,
                    'documents_count'     => 0,
                    'documents_per_index' => new stdClass(),
                ]);
            }

            return array_merge([
                'up' => 0,
            ], $metrics);
        } catch (Throwable $e) {
            $this->handleException('MeilisearchCollector', $e);

            return [
                'up'                  => 0,
                'indexes_count'       => 0,
                'documents_count'     => 0,
                'documents_per_index' => new stdClass(),
            ];
        }
    }

    protected function isEnabled(string $feature): bool
    {
        return (bool)config('prometheus-metrics.collectors.config.meilisearch.enabled', true);
    }

    private function isPackageInstalled(): bool
    {
        return class_exists(Client::class);
    }

    private function getHealth(): array
    {
        $client = $this->getClient();
        $health = $client->health();

        return [
            'status'   => $health['status'] ?? 'unknown',
            'database' => $health['database'] ?? 'unknown',
        ];
    }

    protected function getClient(): Client
    {
        $host = config('meilisearch.host', 'http://127.0.0.1:7700');
        $key = config('meilisearch.key');

        return new Client($host, $key);
    }

    private function getIndexStatistics(): array
    {
        $client = $this->getClient();
        $indexesResult = $client->getIndexes();

        // IndexesResults implements toArray()
        $indexes = is_object($indexesResult) && method_exists($indexesResult, 'toArray')
            ? $indexesResult->toArray()
            : (array)$indexesResult;

        if (empty($indexes['results']) || !is_array($indexes['results'])) {
            return [
                'indexes_count'       => 0,
                'documents_count'     => 0,
                'documents_per_index' => new stdClass(),
            ];
        }

        $perIndex = [];
        $totalDocuments = 0;

        foreach ($indexes['results'] as $index) {
            $uid = $index['uid'] ?? 'unknown';
            $count = (int)($index['numberOfDocuments'] ?? 0);

            $perIndex[$uid] = $count;
            $totalDocuments += $count;
        }

        return [
            'indexes_count'       => count($perIndex),
            'documents_count'     => $totalDocuments,
            'documents_per_index' => $perIndex ?: new stdClass(),
        ];
    }

    public function getName(): string
    {
        return 'meilisearch';
    }
}
