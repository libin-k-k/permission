<?php

namespace Libinkk\Permission\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Libinkk\Permission\Contracts\PermissionRepository as PermissionRepositoryContract;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Support\Tables;

class EloquentPermissionRepository implements PermissionRepositoryContract
{
    public function findByName(string $name, string $guard): ?array
    {
        $permission = Permission::query()
            ->where('guard_name', $guard)
            ->where(function ($query) use ($name) {
                $query->where('name', $name)->orWhere('slug', $name);
            })
            ->first();

        return $permission ? $this->toArray($permission) : null;
    }

    public function allActive(string $guard): array
    {
        return Permission::query()
            ->where('guard_name', $guard)
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn (Permission $permission) => [$permission->name => $this->toArray($permission)])
            ->all();
    }

    public function directPermissionsFor(object $user, string $guard): array
    {
        $now = now();
        $permissions = Tables::permissions();
        $userPermissions = Tables::userPermissions();

        return DB::table($userPermissions)
            ->join($permissions, "{$permissions}.id", '=', "{$userPermissions}.permission_id")
            ->where("{$userPermissions}.user_type", $user->getMorphClass())
            ->where("{$userPermissions}.user_id", $user->getKey())
            ->where("{$permissions}.guard_name", $guard)
            ->where("{$permissions}.is_active", true)
            ->whereNull("{$permissions}.deleted_at")
            ->where("{$userPermissions}.effect", 'allow')
            ->where(function ($query) use ($userPermissions, $now) {
                $query->whereNull("{$userPermissions}.starts_at")
                    ->orWhere("{$userPermissions}.starts_at", '<=', $now);
            })
            ->where(function ($query) use ($userPermissions, $now) {
                $query->whereNull("{$userPermissions}.expires_at")
                    ->orWhere("{$userPermissions}.expires_at", '>', $now);
            })
            ->pluck("{$permissions}.name")
            ->unique()
            ->values()
            ->all();
    }

    public function findOrCreate(string $name, string $guard): array
    {
        $existing = $this->findByName($name, $guard);

        if ($existing) {
            return $existing;
        }

        $parts = explode('.', $name, 2);

        $permission = Permission::query()->create([
            'name' => $name,
            'slug' => Str::slug($name, '.'),
            'resource' => $parts[0] ?? null,
            'action' => $parts[1] ?? null,
            'guard_name' => $guard,
            'is_active' => true,
        ]);

        return $this->toArray($permission);
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(Permission $permission): array
    {
        return [
            'id' => $permission->getKey(),
            'name' => $permission->name,
            'slug' => $permission->slug,
            'resource' => $permission->resource,
            'action' => $permission->action,
            'guard_name' => $permission->guard_name,
            'is_active' => (bool) $permission->is_active,
        ];
    }
}
