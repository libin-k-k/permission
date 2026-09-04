<?php

namespace Libinkk\Permission\Commands;

use Illuminate\Console\Command;
use Libinkk\Permission\Contracts\PermissionCache;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;

class CacheCommand extends Command
{
    protected $signature = 'permission:cache
                            {--guard= : Guard to warm}';

    protected $description = 'Warm permission and role caches';

    public function handle(PermissionCache $cache): int
    {
        $guard = $this->option('guard') ?: config('permission.default_guard', 'web');

        $permissions = Permission::query()
            ->where('guard_name', $guard)
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn (Permission $permission) => [$permission->name => [
                'id' => $permission->getKey(),
                'name' => $permission->name,
                'slug' => $permission->slug,
                'resource' => $permission->resource,
                'action' => $permission->action,
                'group' => $permission->group,
                'guard_name' => $permission->guard_name,
                'is_active' => true,
            ]])
            ->all();

        $cache->put("registry:{$guard}", $permissions, 'permissions');

        $roles = Role::query()
            ->where('guard_name', $guard)
            ->where('is_active', true)
            ->get();

        foreach ($roles as $role) {
            $entries = $role->permissions()
                ->get()
                ->map(fn ($permission) => [
                    'name' => $permission->name,
                    'effect' => (string) ($permission->pivot->effect ?? 'allow'),
                ])
                ->unique(fn (array $row) => $row['name'].'|'.$row['effect'])
                ->values()
                ->all();

            $cache->put("role:{$role->slug}:permissions", $entries, 'roles');
        }

        $this->components->success("Warmed cache for guard [{$guard}] ({$roles->count()} roles, ".count($permissions).' permissions).');

        return self::SUCCESS;
    }
}
