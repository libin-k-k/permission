<?php

namespace Libinkk\Permission\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Libinkk\Permission\Contracts\RoleRepository as RoleRepositoryContract;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Support\Tables;

class EloquentRoleRepository implements RoleRepositoryContract
{
    public function findByNameOrSlug(string $name, string $guard): ?array
    {
        $role = Role::query()
            ->where('guard_name', $guard)
            ->where(function ($query) use ($name) {
                $query->where('slug', $name)->orWhere('name', $name);
            })
            ->first();

        return $role ? $this->toArray($role) : null;
    }

    public function assignedRoles(object $user, string $guard): array
    {
        $now = now();
        $roles = Tables::roles();
        $userRoles = Tables::userRoles();

        return DB::table($userRoles)
            ->join($roles, "{$roles}.id", '=', "{$userRoles}.role_id")
            ->where("{$userRoles}.user_type", $user->getMorphClass())
            ->where("{$userRoles}.user_id", $user->getKey())
            ->where("{$roles}.guard_name", $guard)
            ->where("{$roles}.is_active", true)
            ->whereNull("{$roles}.deleted_at")
            ->where(function ($query) use ($userRoles, $now) {
                $query->whereNull("{$userRoles}.starts_at")
                    ->orWhere("{$userRoles}.starts_at", '<=', $now);
            })
            ->where(function ($query) use ($userRoles, $now) {
                $query->whereNull("{$userRoles}.expires_at")
                    ->orWhere("{$userRoles}.expires_at", '>', $now);
            })
            ->orderByDesc("{$roles}.priority")
            ->get([
                "{$roles}.id",
                "{$roles}.name",
                "{$roles}.slug",
                "{$roles}.priority",
            ])
            ->map(fn ($role) => (array) $role)
            ->all();
    }

    public function permissionNamesForRole(int|string $roleId): array
    {
        return collect($this->permissionEntriesForRole($roleId))
            ->where('effect', 'allow')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, effect: string}>
     */
    public function permissionEntriesForRole(int|string $roleId): array
    {
        $permissions = Tables::permissions();
        $rolePermissions = Tables::rolePermissions();

        return DB::table($rolePermissions)
            ->join($permissions, "{$permissions}.id", '=', "{$rolePermissions}.permission_id")
            ->where("{$rolePermissions}.role_id", $roleId)
            ->where("{$permissions}.is_active", true)
            ->whereNull("{$permissions}.deleted_at")
            ->get([
                "{$permissions}.name",
                "{$rolePermissions}.effect",
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
        $existing = $this->findByNameOrSlug($name, $guard);

        if ($existing) {
            return $existing;
        }

        $role = Role::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'guard_name' => $guard,
            'is_active' => true,
        ]);

        return $this->toArray($role);
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(Role $role): array
    {
        return [
            'id' => $role->getKey(),
            'name' => $role->name,
            'slug' => $role->slug,
            'guard_name' => $role->guard_name,
            'priority' => (int) $role->priority,
            'is_active' => (bool) $role->is_active,
        ];
    }
}
