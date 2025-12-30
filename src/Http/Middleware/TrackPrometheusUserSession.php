<?php

namespace Faktly\LaravelPrometheusMetrics\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrackPrometheusUserSession
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            if (auth()->id()) {
                DB::table('prometheus_metrics_user_sessions')->updateOrInsert(
                    ['session_id' => session()->getId()],
                    [
                        'user_id' => auth()->id(),
                        'last_activity_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        } catch (Exception $e) {
            // Fail silently - not critical for app to work.
            Log::warning(
                sprintf(
                    '[PrometheusMetrics] TrackPrometheusUserSession: %s',
                    $e->getMessage()
                )
            );
        }

        return $response;
    }
}
