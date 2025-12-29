<?php

namespace Faktly\LaravelPrometheusMetrics\Collectors;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use stdClass;
use Throwable;

class PermissionsCollector extends BaseCollector
{
    public function collect(): array
    {
        if (!$this->isEnabled('permissions')) {
            return [];
        }

        if (!$this->isPackageInstalled()) {
            return [
                'roles_count'          => 0,
                'permissions_count'    => 0,
                'users_per_role'       => new stdClass(),
                'permissions_per_role' => new stdClass(),
            ];
        }

        try {
            $rolesCount = $this->getRolesCount();
            $permissionsCount = $this->getPermissionsCount();

            $usersPerRole = config('prometheus-metrics.collectors.config.permissions.track_users_per_role', true)
                ? ($this->getUsersPerRole() ?: new stdClass())
                : new stdClass();

            $permissionsPerRole = config(
                'prometheus-metrics.collectors.config.permissions.track_permissions_per_role',
                true
            )
                ? ($this->getPermissionsPerRole() ?: new stdClass())
                : new stdClass();

            return [
                'roles_count'          => $rolesCount,
                'permissions_count'    => $permissionsCount,
                'users_per_role'       => $usersPerRole,
                'permissions_per_role' => $permissionsPerRole,
            ];
        } catch (Throwable $e) {
            $this->handleException('PermissionsCollector', $e);

            return [
                'roles_count'          => 0,
                'permissions_count'    => 0,
                'users_per_role'       => new stdClass(),
                'permissions_per_role' => new stdClass(),
                'error'                => $e->getMessage(),
            ];
        }
    }

    protected function isEnabled(string $feature): bool
    {
        return (bool)config('prometheus-metrics.collectors.config.permissions.enabled', true);
    }

    private function isPackageInstalled(): bool
    {
        return class_exists(Role::class);
    }

    private function getRolesCount(): int
    {
        try {
            $table = config('permission.table_names.roles', 'roles');

            if (!Schema::hasTable($table)) {
                return 0;
            }

            return (int)DB::table($table)->count();
        } catch (Throwable $e) {
            $this->handleException('getRolesCount', $e);

            return 0;
        }
    }

    private function getPermissionsCount(): int
    {
        try {
            $table = config('permission.table_names.permissions', 'permissions');

            if (!Schema::hasTable($table)) {
                return 0;
            }

            return (int)DB::table($table)->count();
        } catch (Throwable $e) {
            $this->handleException('getPermissionsCount', $e);

            return 0;
        }
    }

    private function getUsersPerRole(): array|stdClass|null
    {
        try {
            $rolesTable = config('permission.table_names.roles', 'roles');
            $modelHasRolesTable = config('permission.table_names.model_has_roles', 'model_has_roles');

            if (!Schema::hasTable($rolesTable) || !Schema::hasTable($modelHasRolesTable)) {
                return null;
            }

            $breakdown = DB::table($rolesTable)
                           ->leftJoin($modelHasRolesTable, $rolesTable . '.id', '=', $modelHasRolesTable . '.role_id')
                           ->select(
                               $rolesTable . '.name',
                               DB::raw('COUNT(' . $modelHasRolesTable . '.model_id) as count')
                           )
                           ->groupBy($rolesTable . '.id', $rolesTable . '.name')
                           ->get()
                           ->pluck('count', 'name')
                           ->toArray();

            return $breakdown ?: new stdClass();
        } catch (Throwable $e) {
            $this->handleException('getUsersPerRole', $e);

            return null;
        }
    }

    private function getPermissionsPerRole(): array|stdClass|null
    {
        try {
            $rolesTable = config('permission.table_names.roles', 'roles');
            $roleHasPermissionsTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');

            if (!Schema::hasTable($rolesTable) || !Schema::hasTable($roleHasPermissionsTable)) {
                return null;
            }

            $breakdown = DB::table($rolesTable)
                           ->leftJoin(
                               $roleHasPermissionsTable,
                               $rolesTable . '.id',
                               '=',
                               $roleHasPermissionsTable . '.role_id'
                           )
                           ->select(
                               $rolesTable . '.name',
                               DB::raw('COUNT(' . $roleHasPermissionsTable . '.permission_id) as count')
                           )
                           ->groupBy($rolesTable . '.id', $rolesTable . '.name')
                           ->get()
                           ->pluck('count', 'name')
                           ->toArray();

            return $breakdown ?: new stdClass();
        } catch (Throwable $e) {
            $this->handleException('getPermissionsPerRole', $e);

            return null;
        }
    }

    public function getName(): string
    {
        return 'permissions';
    }
}
