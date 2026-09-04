<?php

namespace Libinkk\Permission\Concerns;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Libinkk\Permission\Authorization\AuthorizationEngine as Engine;
use Libinkk\Permission\Authorization\Decision;
use Libinkk\Permission\Contracts\AuthorizationEngine;
use Libinkk\Permission\Contracts\PermissionCache;
use Libinkk\Permission\Events\PermissionGranted;
use Libinkk\Permission\Events\PermissionRevoked;
use Libinkk\Permission\Events\RoleAssigned;
use Libinkk\Permission\Events\RoleRemoved;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Support\Tables;

/**
 * @property string|null $guard_name
 */
trait HasAuthorization
{
    public function roles(): MorphToMany
    {
        return $this->morphToMany(
            Role::class,
            'user',
            Tables::userRoles(),
            'user_id',
            'role_id'
        )->withPivot(['scope_type', 'scope_id', 'assigned_by', 'starts_at', 'expires_at'])
            ->withTimestamps();
    }

    public function permissions(): MorphToMany
    {
        return $this->morphToMany(
            Permission::class,
            'user',
            Tables::userPermissions(),
            'user_id',
            'permission_id'
        )->withPivot(['effect', 'scope_type', 'scope_id', 'assigned_by', 'starts_at', 'expires_at'])
            ->withTimestamps();
    }

    public function assignRole(string|Role|array ...$roles): static
    {
        $guard = $this->authorizationGuard();

        foreach ($this->normalizeRoles($roles, $guard) as $role) {
            $this->roles()->syncWithoutDetaching([
                $role->getKey() => $this->assignmentPivot(),
            ]);

            event(new RoleAssigned($this, $role));
        }

        $this->authorizationCache()->forgetUser($this);

        return $this;
    }

    public function removeRole(string|Role|array ...$roles): static
    {
        $guard = $this->authorizationGuard();
        $models = $this->normalizeRoles($roles, $guard, create: false);

        if ($models !== []) {
            $this->roles()->detach(collect($models)->map->getKey()->all());

            foreach ($models as $role) {
                event(new RoleRemoved($this, $role));
            }

            $this->authorizationCache()->forgetUser($this);
        }

        return $this;
    }

    public function syncRoles(string|Role|array ...$roles): static
    {
        $guard = $this->authorizationGuard();
        $models = $this->normalizeRoles($roles, $guard);
        $sync = [];

        foreach ($models as $role) {
            $sync[$role->getKey()] = $this->assignmentPivot();
        }

        $this->roles()->sync($sync);
        $this->authorizationCache()->forgetUser($this);

        return $this;
    }

    public function hasRole(string|Role|array $roles): bool
    {
        $names = $this->roleNameList($roles);
        $assigned = $this->getRoleNames();

        return $names->contains(fn (string $name) => $assigned->contains($name));
    }

    public function hasAnyRole(string|Role|array ...$roles): bool
    {
        return $this->hasRole(collect($roles)->flatten()->all());
    }

    public function hasAllRoles(string|Role|array ...$roles): bool
    {
        $names = $this->roleNameList(collect($roles)->flatten()->all());
        $assigned = $this->getRoleNames();

        return $names->every(fn (string $name) => $assigned->contains($name));
    }

    public function givePermissionTo(string|Permission|array ...$permissions): static
    {
        $guard = $this->authorizationGuard();

        foreach ($this->normalizePermissions($permissions, $guard) as $permission) {
            $this->permissions()->syncWithoutDetaching([
                $permission->getKey() => $this->assignmentPivot(['effect' => 'allow']),
            ]);

            event(new PermissionGranted($this, $permission));
        }

        $this->authorizationCache()->forgetUser($this);

        return $this;
    }

    public function revokePermissionTo(string|Permission|array ...$permissions): static
    {
        $guard = $this->authorizationGuard();
        $models = $this->normalizePermissions($permissions, $guard, create: false);

        if ($models !== []) {
            $this->permissions()->detach(collect($models)->map->getKey()->all());

            foreach ($models as $permission) {
                event(new PermissionRevoked($this, $permission));
            }

            $this->authorizationCache()->forgetUser($this);
        }

        return $this;
    }

    public function syncPermissions(string|Permission|array ...$permissions): static
    {
        $guard = $this->authorizationGuard();
        $models = $this->normalizePermissions($permissions, $guard);
        $sync = [];

        foreach ($models as $permission) {
            $sync[$permission->getKey()] = $this->assignmentPivot(['effect' => 'allow']);
        }

        $this->permissions()->sync($sync);
        $this->authorizationCache()->forgetUser($this);

        return $this;
    }

    public function hasPermissionTo(string|Permission $permission, array $arguments = []): bool
    {
        $name = $permission instanceof Permission ? $permission->name : $permission;

        return $this->authorizationEngine()->allows($this, $name, $arguments);
    }

    /**
     * @param  iterable|\UnitEnum|string  $abilities
     * @param  array<mixed>|mixed  $arguments
     */
    public function can($abilities, $arguments = []): bool
    {
        if (Engine::isResolving()) {
            return collect(Arr::wrap($abilities))->every(
                fn ($ability) => $this->authorizationEngine()->allows(
                    $this,
                    $this->normalizeAbility($ability),
                    Arr::wrap($arguments),
                )
            );
        }

        return app(Gate::class)->forUser($this)->check($abilities, $arguments);
    }

    public function canAny($abilities, $arguments = []): bool
    {
        foreach ((array) $abilities as $ability) {
            if ($this->can($ability, $arguments)) {
                return true;
            }
        }

        return false;
    }

    public function canAll(array $permissions, mixed $arguments = []): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->can($permission, $arguments)) {
                return false;
            }
        }

        return $permissions !== [];
    }

    public function authorizeFor(string $permission, mixed $resource = null): Decision
    {
        $arguments = $resource === null ? [] : [$resource];

        return $this->authorizationEngine()->decide($this, $permission, $arguments);
    }

    public function explain(string $permission, mixed $resource = null): array
    {
        return $this->authorizeFor($permission, $resource)->toArray();
    }

    public function getRoleNames(): Collection
    {
        $roles = app(\Libinkk\Permission\Permissions\PermissionResolver::class)
            ->rolesFor($this, $this->authorizationGuard());

        return collect($roles)
            ->flatMap(fn (array $role) => [$role['slug'], $role['name']])
            ->unique()
            ->values();
    }

    public function getPermissionNames(): Collection
    {
        $guard = $this->authorizationGuard();
        $map = app(\Libinkk\Permission\Permissions\PermissionResolver::class)->permissionMapFor($this, $guard);

        return collect(array_keys($map))->values();
    }

    public function authorizationGuard(): string
    {
        if (isset($this->guard_name) && is_string($this->guard_name) && $this->guard_name !== '') {
            return $this->guard_name;
        }

        return (string) config('permission.default_guard', 'web');
    }

    /**
     * @param  array<int, string|Role|array>  $roles
     * @return list<Role>
     */
    protected function normalizeRoles(array $roles, string $guard, bool $create = true): array
    {
        return collect($roles)
            ->flatten()
            ->filter()
            ->map(function (string|Role $role) use ($guard, $create) {
                if ($role instanceof Role) {
                    return $role;
                }

                return $create
                    ? Role::findOrCreate($role, $guard)
                    : Role::query()
                        ->where('guard_name', $guard)
                        ->where(fn ($query) => $query->where('slug', $role)->orWhere('name', $role))
                        ->first();
            })
            ->filter()
            ->unique(fn (Role $role) => $role->getKey())
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string|Permission|array>  $permissions
     * @return list<Permission>
     */
    protected function normalizePermissions(array $permissions, string $guard, bool $create = true): array
    {
        return collect($permissions)
            ->flatten()
            ->filter()
            ->map(function (string|Permission $permission) use ($guard, $create) {
                if ($permission instanceof Permission) {
                    return $permission;
                }

                return $create
                    ? Permission::findOrCreate($permission, $guard)
                    : Permission::query()
                        ->where('guard_name', $guard)
                        ->where(fn ($query) => $query->where('name', $permission)->orWhere('slug', $permission))
                        ->first();
            })
            ->filter()
            ->unique(fn (Permission $permission) => $permission->getKey())
            ->values()
            ->all();
    }

    /**
     * @param  string|Role|array<int, string|Role>  $roles
     */
    protected function roleNameList(string|Role|array $roles): Collection
    {
        return collect(is_array($roles) ? $roles : [$roles])
            ->map(fn (string|Role $role) => $role instanceof Role ? $role->slug : $role)
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function assignmentPivot(array $extra = []): array
    {
        return array_merge([
            'scope_type' => 'global',
            'scope_id' => '',
            'assigned_by' => null,
            'starts_at' => now(),
            'expires_at' => null,
        ], $extra);
    }

    protected function authorizationCache(): PermissionCache
    {
        return app(PermissionCache::class);
    }

    protected function authorizationEngine(): AuthorizationEngine
    {
        return app(AuthorizationEngine::class);
    }

    protected function normalizeAbility(mixed $ability): string
    {
        if ($ability instanceof \UnitEnum) {
            return $ability->name;
        }

        return (string) $ability;
    }
}
