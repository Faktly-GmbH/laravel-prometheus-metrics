<?php

namespace Faktly\LaravelPrometheusMetrics\Collectors;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Throwable;

class UserCollector extends BaseCollector
{
    public function collect(): array
    {
        if (!$this->isEnabled('user')) {
            return [];
        }

        try {
            $userModelClass = $this->getUserModelClass();

            if (!$userModelClass || !class_exists($userModelClass)) {
                return [
                    'count'        => 0,
                    'active_count' => 0,
                    'model'        => $userModelClass,
                    'error'        => 'User model not found',
                ];
            }

            /** @var Model $userModel */
            $userModel = new $userModelClass();

            return [
                'count'        => $this->countAllUsers($userModel),
                'active_count' => $this->countActiveUsers($userModel),
                'model'        => $userModelClass,
            ];
        } catch (Throwable $e) {
            $this->handleException('UserCollector', $e);

            return [
                'count'        => 0,
                'active_count' => 0,
                'model'        => $this->getUserModelClass(),
                'error'        => $e->getMessage(),
            ];
        }
    }

    protected function isEnabled($feature): bool
    {
        return (bool)Config::get('prometheus-metrics.collectors.config.user.enabled', true);
    }

    private function getUserModelClass(): ?string
    {
        $model = Config::get('auth.providers.users.model');

        return (is_string($model) && $model !== '') ? $model : null;
    }

    private function countAllUsers(Model $userModel): int
    {
        try {
            return (int)$userModel->newQuery()->count();
        } catch (Throwable $e) {
            $this->handleException('UserCollector.countAllUsers', $e);

            return 0;
        }
    }

    /**
     * "Active" heuristic:
     * - active boolean column
     * - status column equals 'active'
     * - else fall back to total count
     */
    private function countActiveUsers(Model $userModel): int
    {
        try {
            $query = $userModel->newQuery();

            $connection = $userModel->getConnection();
            $table = $userModel->getTable();

            // hasColumn exists on Laravel's schema builder
            if ($connection->getSchemaBuilder()->hasColumn($table, 'active')) {
                return (int)$query->where('active', true)->count();
            }

            if ($connection->getSchemaBuilder()->hasColumn($table, 'status')) {
                return (int)$query->where('status', 'active')->count();
            }

            return $this->countAllUsers($userModel);
        } catch (Throwable $e) {
            $this->handleException('UserCollector.countActiveUsers', $e);

            return 0;
        }
    }

    public function getName(): string
    {
        return 'user';
    }
}
