<?php

namespace Faktly\LaravelPrometheusMetrics\Collectors;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\EventSourcing\EventSourcingServiceProvider;
use stdClass;
use Throwable;

class EventSourcingCollector extends BaseCollector
{
    public function collect(): array
    {
        if (!$this->isEnabled('event_sourcing')) {
            return [];
        }

        $minutes = (int)config('prometheus-metrics.collectors.config.event_sourcing.window_minutes', 60 * 24);
        $seconds = $minutes * 60;

        if (!$this->isPackageInstalled()) {
            return [
                'events_count'          => 0,
                'events_window_count'   => [
                    sprintf('%ds', $seconds) => 0,
                ],
                'events_per_type_total' => new stdClass(),
            ];
        }

        try {
            $eventsTotal = $this->getTotalEventsCount();
            $eventsLastWindow = $this->getEventsInWindow($minutes);
            $eventsPerType = config(
                'prometheus-metrics.collectors.config.event_sourcing.include_per_type_breakdown',
                true
            )
                ? $this->getEventsPerTypeBreakdown()
                : [];

            return [
                'events_count'          => $eventsTotal ?? 0,
                'events_window_count'   => [sprintf('%ds', $seconds) => $eventsLastWindow ?? 0],
                'events_per_type_total' => $eventsPerType['events_count'] ?? new stdClass(),
            ];
        } catch (Throwable $e) {
            $this->handleException('EventSourcingCollector', $e);

            return [
                'events_count'          => 0,
                'events_window_count'   => [sprintf('%ds', $seconds) => 0,],
                'events_per_type_total' => new stdClass(),
                'error'                 => $e->getMessage(),
            ];
        }
    }

    protected function isEnabled(string $feature): bool
    {
        return config('prometheus-metrics.collectors.config.event_sourcing.enabled', true);
    }

    private function isPackageInstalled(): bool
    {
        return class_exists(EventSourcingServiceProvider::class);
    }

    private function getTotalEventsCount(): int
    {
        try {
            $total = 0;

            foreach ($this->getStoredEventTables() as $table) {
                if (!DB::getSchemaBuilder()->hasTable($table)) {
                    continue;
                }

                $total += DB::table($table)->count();
            }

            return $total;
        } catch (Throwable $e) {
            $this->handleException('getTotalEventsCount', $e);

            return 0;
        }
    }

    /**
     * Return all configured stored event tables, including the default.
     *
     * @return string[]
     */
    private function getStoredEventTables(): array
    {
        return array_unique(
            array_filter(
                array_merge(
                    // Spatie uses stored_events by default
                    ['stored_events'],
                    (array)config('prometheus-metrics.collectors.config.event_sourcing.extra_stored_event_tables', [])
                )
            )
        );
    }

    private function getEventsInWindow($minutes): int
    {
        try {
            $from = Carbon::now()->subMinutes($minutes);
            $total = 0;

            foreach ($this->getStoredEventTables() as $table) {
                if (!DB::getSchemaBuilder()->hasTable($table)) {
                    continue;
                }

                $total += DB::table($table)
                            ->where('created_at', '>=', $from)
                            ->count();
            }

            return $total;
        } catch (Throwable $e) {
            $this->handleException('getEventsInWindow', $e);

            return 0;
        }
    }

    private function getEventsPerTypeBreakdown(): array
    {
        try {
            $all = [];

            foreach ($this->getStoredEventTables() as $table) {
                if (!DB::getSchemaBuilder()->hasTable($table)) {
                    continue;
                }

                $rows = DB::table($table)
                          ->select('event_class', DB::raw('COUNT(*) as count'))
                          ->groupBy('event_class')
                          ->get()
                          ->pluck('count', 'event_class')
                          ->toArray();

                foreach ($rows as $eventClass => $count) {
                    $name = class_basename($eventClass);
                    if (!isset($all[$name])) {
                        $all[$name] = 0;
                    }
                    $all[$name] += (int)$count;
                }
            }

            return [
                'events_count' => $all ?: new stdClass(),
            ];
        } catch (Throwable $e) {
            $this->handleException('getEventsPerTypeBreakdown', $e);

            return [];
        }
    }

    public function getName(): string
    {
        return 'event_sourcing';
    }
}
