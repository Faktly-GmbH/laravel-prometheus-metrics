<?php

namespace Faktly\LaravelPrometheusMetrics\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupPrometheusUserSessionsCommand extends Command
{
    protected $signature = 'prometheus:clean-user-sessions {--days=7}';

    protected $description = 'Clean old tracked Prometheus user session records';

    public function handle()
    {
        $days = $this->option('days');
        $deleted = DB::table('prometheus_metrics_user_sessions')
                     ->where('last_activity_at', '<', now()->subDays($days))
                     ->delete();

        $this->info("Deleted {$deleted} old session records.");

        return self::SUCCESS;
    }
}
