<?php

namespace Faktly\LaravelPrometheusMetrics\Tests\Unit\Collectors;

use Faktly\LaravelPrometheusMetrics\Collectors\PermissionsCollector;
use Faktly\LaravelPrometheusMetrics\Tests\TestCase;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Spatie\Permission\Models\Role;
use stdClass;

class PermissionsCollectorTest extends TestCase
{
    public function test_returns_empty_array_when_disabled(): void
    {
        Config::set('prometheus-metrics.collectors.config.permissions.enabled', false);

        $result = (new PermissionsCollector())->collect();

        $this->assertSame([], $result);
    }

    public function test_returns_defaults_when_spatie_permission_is_not_installed(): void
    {
        if (class_exists(Role::class)) {
            $this->markTestSkipped('spatie/laravel-permission is installed in this test runtime.');
        }

        $result = (new PermissionsCollector())->collect();

        $this->assertSame(0, $result['roles_count']);
        $this->assertSame(0, $result['permissions_count']);
        $this->assertEquals(new stdClass(), $result['users_per_role']);
        $this->assertEquals(new stdClass(), $result['permissions_per_role']);
    }

    public function test_collect_returns_counts_and_breakdowns(): void
    {
        if (!class_exists(Role::class)) {
            $this->markTestSkipped('spatie/laravel-permission is not installed in this test runtime.');
        }

        Schema::shouldReceive('hasTable')
              ->atLeast()
              ->once()
              ->with(Mockery::type('string'))
              ->andReturn(true);

        DB::shouldReceive('raw')
          ->andReturnUsing(fn (string $sql) => new Expression($sql));

        $rolesCountQuery = Mockery::mock();
        $rolesCountQuery->shouldReceive('count')->once()->andReturn(3);

        $permissionsCountQuery = Mockery::mock();
        $permissionsCountQuery->shouldReceive('count')->once()->andReturn(0);

        $usersPerRoleQuery = Mockery::mock();
        $usersPerRoleQuery->shouldReceive('leftJoin')->once()->andReturnSelf();
        $usersPerRoleQuery->shouldReceive('select')->once()->andReturnSelf();
        $usersPerRoleQuery->shouldReceive('groupBy')->once()->andReturnSelf();
        $usersPerRoleGet = Mockery::mock();
        $usersPerRolePluck = Mockery::mock();
        $usersPerRoleQuery->shouldReceive('get')->once()->andReturn($usersPerRoleGet);
        $usersPerRoleGet->shouldReceive('pluck')->with('count', 'name')->once()->andReturn($usersPerRolePluck);
        $usersPerRolePluck->shouldReceive('toArray')->once()->andReturn([
            'admin'   => 1,
            'user'    => 2,
            'manager' => 0,
        ]);

        $permissionsPerRoleQuery = Mockery::mock();
        $permissionsPerRoleQuery->shouldReceive('leftJoin')->once()->andReturnSelf();
        $permissionsPerRoleQuery->shouldReceive('select')->once()->andReturnSelf();
        $permissionsPerRoleQuery->shouldReceive('groupBy')->once()->andReturnSelf();
        $permissionsPerRoleGet = Mockery::mock();
        $permissionsPerRolePluck = Mockery::mock();
        $permissionsPerRoleQuery->shouldReceive('get')->once()->andReturn($permissionsPerRoleGet);
        $permissionsPerRoleGet->shouldReceive('pluck')->with('count', 'name')->once()->andReturn(
            $permissionsPerRolePluck
        );
        $permissionsPerRolePluck->shouldReceive('toArray')->once()->andReturn([
            'admin'   => 0,
            'user'    => 0,
            'manager' => 0,
        ]);

        // Enforce exact call order for the three DB::table('roles') calls:
        DB::shouldReceive('table')->with('roles')->once()->ordered()->andReturn($rolesCountQuery);
        DB::shouldReceive('table')->with('permissions')->once()->ordered()->andReturn($permissionsCountQuery);
        DB::shouldReceive('table')->with('roles')->once()->ordered()->andReturn($usersPerRoleQuery);
        DB::shouldReceive('table')->with('roles')->once()->ordered()->andReturn($permissionsPerRoleQuery);

        $result = (new PermissionsCollector())->collect();

        $this->assertSame(3, $result['roles_count']);
        $this->assertSame(0, $result['permissions_count']);
        $this->assertSame(['admin' => 1, 'user' => 2, 'manager' => 0], $result['users_per_role']);
        $this->assertSame(['admin' => 0, 'user' => 0, 'manager' => 0], $result['permissions_per_role']);
    }

    public function test_collect_returns_empty_objects_when_breakdowns_disabled(): void
    {
        if (!class_exists(Role::class)) {
            $this->markTestSkipped('spatie/laravel-permission is not installed in this test runtime.');
        }

        Config::set('prometheus-metrics.collectors.config.permissions.track_users_per_role', false);
        Config::set('prometheus-metrics.collectors.config.permissions.track_permissions_per_role', false);

        Schema::shouldReceive('hasTable')->with('roles')->andReturn(true);
        Schema::shouldReceive('hasTable')->with('permissions')->andReturn(true);

        $rolesCountQuery = Mockery::mock();
        $rolesCountQuery->shouldReceive('count')->once()->andReturn(3);

        $permissionsCountQuery = Mockery::mock();
        $permissionsCountQuery->shouldReceive('count')->once()->andReturn(0);

        DB::shouldReceive('table')->with('roles')->once()->andReturn($rolesCountQuery);
        DB::shouldReceive('table')->with('permissions')->once()->andReturn($permissionsCountQuery);

        $result = (new PermissionsCollector())->collect();

        $this->assertSame(3, $result['roles_count']);
        $this->assertSame(0, $result['permissions_count']);
        $this->assertEquals(new stdClass(), $result['users_per_role']);
        $this->assertEquals(new stdClass(), $result['permissions_per_role']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('prometheus-metrics.collectors.config.permissions.enabled', true);
        Config::set('prometheus-metrics.collectors.config.permissions.track_users_per_role', true);
        Config::set('prometheus-metrics.collectors.config.permissions.track_permissions_per_role', true);

        Config::set('permission.table_names.roles', 'roles');
        Config::set('permission.table_names.permissions', 'permissions');
        Config::set('permission.table_names.model_has_roles', 'model_has_roles');
        Config::set('permission.table_names.role_has_permissions', 'role_has_permissions');
    }
}
