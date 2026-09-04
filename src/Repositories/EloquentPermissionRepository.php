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

    public function directPermissionsFor(object $user, string $guard, array $scopePivots = []): array
    {
        return collect($this->directPermissionEntriesFor($user, $guard, $scopePivots))
            ->where('effect', 'allow')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array{scope_type: string, scope_id: string}>  $scopePivots
     * @return list<array{name: string, effect: string}>
     */
    public function directPermissionEntriesFor(object $user, string $guard, array $scopePivots = []): array
    {
        $now = now();
        $permissions = Tables::permissions();
        $userPermissions = Tables::userPermissions();

        $query = DB::table($userPermissions)
            ->join($permissions, "{$permissions}.id", '=', "{$userPermissions}.permission_id")
            ->where("{$userPermissions}.user_type", $user->getMorphClass())
            ->where("{$userPermissions}.user_id", $user->getKey())
            ->where("{$permissions}.guard_name", $guard)
            ->where("{$permissions}.is_active", true)
            ->whereNull("{$permissions}.deleted_at")
            ->where(function ($query) use ($userPermissions, $now) {
                $query->whereNull("{$userPermissions}.starts_at")
                    ->orWhere("{$userPermissions}.starts_at", '<=', $now);
            })
            ->where(function ($query) use ($userPermissions, $now) {
                $query->whereNull("{$userPermissions}.expires_at")
                    ->orWhere("{$userPermissions}.expires_at", '>', $now);
            });

        $this->constrainScope($query, $userPermissions, $scopePivots);

        return $query
            ->get([
                "{$permissions}.name",
                "{$userPermissions}.effect",
            ])
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'effect' => (string) ($row->effect ?: 'allow'),
            ])
            ->unique(fn (array $row) => $row['name'].'|'.$row['effect'])
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
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  list<array{scope_type: string, scope_id: string}>  $scopePivots
     */
    protected function constrainScope($query, string $table, array $scopePivots): void
    {
        if ($scopePivots === []) {
            return;
        }

        $query->where(function ($outer) use ($table, $scopePivots) {
            foreach ($scopePivots as $index => $pivot) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $outer->{$method}(function ($inner) use ($table, $pivot) {
                    $inner->where("{$table}.scope_type", $pivot['scope_type'])
                        ->where("{$table}.scope_id", $pivot['scope_id']);
                });
            }
        });
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
            'group' => $permission->group,
            'guard_name' => $permission->guard_name,
            'is_active' => (bool) $permission->is_active,
            'risk_level' => $permission->risk_level,
            'is_dangerous' => (bool) $permission->is_dangerous,
        ];
    }
}
