<?php

namespace Libinkk\Permission\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Libinkk\Permission\Cache\CacheMetrics;
use Libinkk\Permission\Version;
use Libinkk\Permission\Debug\UnusedPermissionFinder;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;

class PermissionDoctor
{
    public function __construct(
        protected PermissionValidator $validator,
        protected UnusedPermissionFinder $unused,
    ) {
    }

    /**
     * @return array{healthy: bool, checks: list<array{status: string, label: string, detail?: string}>, report: array<string, mixed>}
     */
    public function run(?string $guard = null): array
    {
        $guard ??= (string) config('permission.default_guard', 'web');
        $validation = $this->validator->validate($guard);

        $permissionCount = Permission::query()->when($guard, fn ($q) => $q->where('guard_name', $guard))->count();
        $roleCount = Role::query()->when($guard, fn ($q) => $q->where('guard_name', $guard))->count();
        $activePermissions = Permission::query()->where('is_active', true)->when($guard, fn ($q) => $q->where('guard_name', $guard))->count();
        $activeRoles = Role::query()->where('is_active', true)->when($guard, fn ($q) => $q->where('guard_name', $guard))->count();

        $checks = [
            $this->ok('libinkk/permission '.Version::VERSION),
            $this->ok("{$permissionCount} permissions registered ({$activePermissions} active)"),
            $this->ok("{$roleCount} roles registered ({$activeRoles} active)"),
            $this->tableCheck(Tables::roles()),
            $this->tableCheck(Tables::permissions()),
            $this->tableCheck(Tables::rolePermissions()),
            $this->tableCheck(Tables::userRoles()),
            $this->tableCheck(Tables::userPermissions()),
            $this->tableCheck(Tables::get('scopes', 'scopes')),
            $this->tableCheck(Tables::get('user_scopes', 'user_scopes')),
            $this->tableCheck(Tables::permissionDelegations()),
            $this->tableCheck(Tables::permissionVersions()),
            $this->tableCheck(Tables::authorizationAudits()),
            $this->cacheCheck(),
            $this->redisLayerCheck(),
            $this->decisionCacheCheck(),
            $this->indexHint(),
            $this->expiredDelegationsCheck(),
        ];

        foreach ($validation['errors'] as $error) {
            $checks[] = $this->fail($error['message']);
        }

        foreach ($validation['warnings'] as $warning) {
            $checks[] = $this->warn($warning['message']);
        }

        $unused = $this->unused->find($guard);
        $metrics = app(CacheMetrics::class)->snapshot();

        if ($unused['total'] > 0) {
            $checks[] = $this->warn("{$unused['total']} unused permissions (run permission:unused)");
        } else {
            $checks[] = $this->ok('No unused permissions');
        }

        $healthy = $validation['ok'] && collect($checks)->every(fn (array $check) => $check['status'] !== 'fail');

        return [
            'healthy' => $healthy,
            'checks' => $checks,
            'report' => [
                'version' => Version::VERSION,
                'permissions' => $permissionCount,
                'roles' => $roleCount,
                'unused_permissions' => $unused['total'],
                'unused' => $unused,
                'cache' => $metrics,
                'validation' => $validation,
            ],
        ];
    }

    /**
     * @return array{status: string, label: string, detail?: string}
     */
    protected function tableCheck(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return $this->fail("Missing table [{$table}]");
        }

        return $this->ok("Table [{$table}] present");
    }

    /**
     * @return array{status: string, label: string, detail?: string}
     */
    protected function cacheCheck(): array
    {
        if (! config('permission.cache.enabled', true)) {
            return $this->warn('Permission cache is disabled');
        }

        try {
            $store = config('permission.cache.store') ?: config('cache.default');
            Cache::store($store)->put('libinkk:permission:doctor', true, 5);
            Cache::store($store)->forget('libinkk:permission:doctor');

            return $this->ok("Permission cache healthy (store: {$store})");
        } catch (\Throwable $e) {
            return $this->fail('Permission cache unhealthy: '.$e->getMessage());
        }
    }

    /**
     * @return array{status: string, label: string, detail?: string}
     */
    protected function redisLayerCheck(): array
    {
        if (! config('permission.cache.redis.enabled', false)) {
            return $this->ok('Redis L3 cache opt-in is off (L2 store only)');
        }

        $store = (string) config('permission.cache.redis.store', 'redis');

        try {
            Cache::store($store)->put('libinkk:permission:doctor:l3', true, 5);
            Cache::store($store)->forget('libinkk:permission:doctor:l3');

            return $this->ok("Redis L3 cache healthy (store: {$store})");
        } catch (\Throwable $e) {
            return $this->fail('Redis L3 cache unhealthy: '.$e->getMessage());
        }
    }

    /**
     * @return array{status: string, label: string}
     */
    protected function decisionCacheCheck(): array
    {
        if (! config('permission.cache.decision_cache.enabled', true)) {
            return $this->warn('Decision cache is disabled');
        }

        $metrics = app(CacheMetrics::class)->snapshot();

        return $this->ok(
            'Decision cache enabled (hit rate '.number_format($metrics['hit_rate'] * 100, 1).'%)'
        );
    }

    /**
     * @return array{status: string, label: string, detail?: string}
     */
    protected function indexHint(): array
    {
        return $this->ok('Core authorization tables reachable');
    }

    /**
     * @return array{status: string, label: string}
     */
    protected function expiredDelegationsCheck(): array
    {
        if (! Schema::hasTable(Tables::permissionDelegations())) {
            return $this->warn('Delegation table missing');
        }

        $expired = (int) DB::table(Tables::permissionDelegations())
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->count();

        if ($expired > 0) {
            return $this->warn("{$expired} expired delegations still marked active");
        }

        return $this->ok('No stale expired delegations');
    }

    /**
     * @return array{status: string, label: string}
     */
    protected function ok(string $label): array
    {
        return ['status' => 'ok', 'label' => $label];
    }

    /**
     * @return array{status: string, label: string}
     */
    protected function warn(string $label): array
    {
        return ['status' => 'warn', 'label' => $label];
    }

    /**
     * @return array{status: string, label: string}
     */
    protected function fail(string $label): array
    {
        return ['status' => 'fail', 'label' => $label];
    }
}
