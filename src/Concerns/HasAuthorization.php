<?php

namespace Libinkk\Permission\Concerns;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Libinkk\Permission\Authorization\AuthorizationContext;
use Libinkk\Permission\Authorization\AuthorizationEngine as Engine;
use Libinkk\Permission\Authorization\Decision;
use Libinkk\Permission\Contracts\AuthorizationEngine;
use Libinkk\Permission\Contracts\PermissionCache;
use Libinkk\Permission\Delegation\Delegation;
use Libinkk\Permission\Delegation\DelegationManager;
use Libinkk\Permission\Events\PermissionGranted;
use Libinkk\Permission\Events\PermissionRevoked;
use Libinkk\Permission\Events\RoleAssigned;
use Libinkk\Permission\Events\RoleRemoved;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Scopes\ScopeResolver;
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

    public function assignRole(mixed ...$roles): static
    {
        [$roles, $options] = $this->extractAssignmentOptions($roles);
        [$roles, $scope] = $this->extractScopeArgument($roles);
        $guard = $this->authorizationGuard();
        $pivot = $this->assignmentPivot(array_merge($this->scopePivot($scope), $options));

        foreach ($this->normalizeRoles($roles, $guard) as $role) {
            $this->roles()->syncWithoutDetaching([
                $role->getKey() => $pivot,
            ]);

            event(new RoleAssigned($this, $role));
        }

        if ($scope) {
            $this->scopeResolver()->grantUser($this, $scope);
        }

        $this->authorizationCache()->forgetUser($this);

        return $this;
    }

    public function removeRole(mixed ...$roles): static
    {
        [$roles, $scope] = $this->extractScopeArgument($roles);
        $guard = $this->authorizationGuard();
        $models = $this->normalizeRoles($roles, $guard, create: false);

        if ($models !== []) {
            $ids = collect($models)->map->getKey()->all();
            $query = DB::table(Tables::userRoles())
                ->where('user_type', $this->getMorphClass())
                ->where('user_id', $this->getKey())
                ->whereIn('role_id', $ids);

            if ($scope !== null) {
                $pivot = $this->scopePivot($scope);
                $query->where('scope_type', $pivot['scope_type'])->where('scope_id', $pivot['scope_id']);
            }

            $query->delete();

            foreach ($models as $role) {
                event(new RoleRemoved($this, $role));
            }

            $this->authorizationCache()->forgetUser($this);
        }

        return $this;
    }

    public function syncRoles(mixed ...$roles): static
    {
        [$roles, $options] = $this->extractAssignmentOptions($roles);
        [$roles, $scope] = $this->extractScopeArgument($roles);
        $guard = $this->authorizationGuard();
        $models = $this->normalizeRoles($roles, $guard);
        $sync = [];
        $pivot = $this->assignmentPivot(array_merge($this->scopePivot($scope), $options));

        foreach ($models as $role) {
            $sync[$role->getKey()] = $pivot;
        }

        if ($scope !== null) {
            $scopePivot = $this->scopePivot($scope);
            DB::table(Tables::userRoles())
                ->where('user_type', $this->getMorphClass())
                ->where('user_id', $this->getKey())
                ->where('scope_type', $scopePivot['scope_type'])
                ->where('scope_id', $scopePivot['scope_id'])
                ->delete();

            foreach ($models as $role) {
                $this->roles()->syncWithoutDetaching([
                    $role->getKey() => $pivot,
                ]);
            }
        } else {
            $this->roles()->sync($sync);
        }

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

    public function givePermissionTo(mixed ...$permissions): static
    {
        return $this->assignPermissionEffect($permissions, 'allow');
    }

    public function denyPermissionTo(mixed ...$permissions): static
    {
        return $this->assignPermissionEffect($permissions, 'deny');
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

    public function delegate(
        string $permission,
        mixed $to,
        mixed $until = null,
        mixed $startsAt = null,
        mixed $scope = null,
        mixed $resource = null,
        ?string $reason = null,
    ): Delegation {
        return app(DelegationManager::class)->create(
            from: $this,
            to: $to,
            permission: $permission,
            until: $until,
            startsAt: $startsAt,
            scope: $scope,
            resource: $resource,
            reason: $reason,
        );
    }

    public function revokeDelegation(Delegation|int|string $delegation, ?string $reason = null): Delegation
    {
        $model = $delegation instanceof Delegation
            ? $delegation
            : Delegation::query()->findOrFail($delegation);

        return app(DelegationManager::class)->revoke($model, $reason);
    }

    /**
     * @param  array<int, string|Permission|array>  $permissions
     */
    protected function assignPermissionEffect(array $permissions, string $effect): static
    {
        [$permissions, $options] = $this->extractAssignmentOptions($permissions);
        [$permissions, $scope] = $this->extractScopeArgument($permissions);
        $guard = $this->authorizationGuard();
        $pivot = $this->assignmentPivot(array_merge($this->scopePivot($scope), ['effect' => $effect], $options));

        foreach ($this->normalizePermissions($permissions, $guard) as $permission) {
            $this->permissions()->syncWithoutDetaching([
                $permission->getKey() => $pivot,
            ]);

            event(new PermissionGranted($this, $permission));
        }

        if ($scope) {
            $this->scopeResolver()->grantUser($this, $scope);
        }

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

    /**
     * Export all roles and effective permissions for this user (totals, sources, groups).
     *
     * @return array<string, mixed>
     */
    public function exportAccess(?string $guard = null): array
    {
        return app(\Libinkk\Permission\Authorization\UserAccessExporter::class)
            ->export($this, $guard ?? $this->authorizationGuard());
    }

    public function exportAccessJson(?string $guard = null): string
    {
        return app(\Libinkk\Permission\Authorization\UserAccessExporter::class)
            ->toJson($this, $guard ?? $this->authorizationGuard());
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

        return collect($map)
            ->filter(fn (array $entry) => ($entry['effect'] ?? 'allow') === 'allow')
            ->keys()
            ->values();
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

    /**
     * @param  array<int, mixed>  $items
     * @return array{0: array<int, mixed>, 1: mixed}
     */
    protected function extractScopeArgument(array $items): array
    {
        if ($items === []) {
            return [[], AuthorizationContext::currentTarget()];
        }

        $last = $items[array_key_last($items)];

        if ($this->isScopeArgument($last)) {
            array_pop($items);

            return [array_values($items), $last];
        }

        return [$items, AuthorizationContext::currentTarget()];
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return array{0: array<int, mixed>, 1: array<string, mixed>}
     */
    protected function extractAssignmentOptions(array $items): array
    {
        $keys = [
            'expiresAt' => 'expires_at',
            'expires_at' => 'expires_at',
            'startsAt' => 'starts_at',
            'starts_at' => 'starts_at',
            'until' => 'expires_at',
        ];

        $options = [];

        foreach ($items as $key => $value) {
            if (is_string($key) && isset($keys[$key])) {
                $options[$keys[$key]] = $value;
                unset($items[$key]);
            }
        }

        return [array_values($items), $options];
    }

    protected function isScopeArgument(mixed $value): bool
    {
        if ($value instanceof Role || $value instanceof Permission || $value instanceof DateTimeInterface || is_string($value) || is_array($value)) {
            return false;
        }

        return is_object($value);
    }

    /**
     * @return array{scope_type: string, scope_id: string}
     */
    protected function scopePivot(mixed $scope): array
    {
        return $this->scopeResolver()->pivotFor($scope);
    }

    protected function scopeResolver(): ScopeResolver
    {
        return app(ScopeResolver::class);
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
