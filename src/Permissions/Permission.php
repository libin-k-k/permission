<?php

namespace Libinkk\Permission\Permissions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Libinkk\Permission\Cache\PermissionFake;
use Libinkk\Permission\Contracts\PermissionCache;
use Libinkk\Permission\Events\PermissionCreated;
use Libinkk\Permission\Events\PermissionDeleted;
use Libinkk\Permission\Events\PermissionUpdated;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Support\Tables;
use Libinkk\Permission\Support\UsesConfiguredKeys;

class Permission extends Model
{
    use SoftDeletes;
    use UsesConfiguredKeys;

    protected $guarded = [];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'is_dangerous' => 'boolean',
        'requires_audit' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $permission) {
            if (! $permission->slug) {
                $permission->slug = Str::slug((string) $permission->name, '.');
            }

            if ((! $permission->resource || ! $permission->action) && is_string($permission->name) && str_contains($permission->name, '.')) {
                [$resource, $action] = explode('.', $permission->name, 2);
                $permission->resource ??= $resource;
                $permission->action ??= $action;
            }

            $permission->guard_name ??= config('permission.default_guard', 'web');
            $permission->scope_type ??= 'global';
            $permission->risk_level ??= 'LOW';
        });

        static::created(function (self $permission) {
            app(PermissionCache::class)->forgetPermission($permission->name);
            event(new PermissionCreated($permission));
        });

        static::updated(function (self $permission) {
            app(PermissionCache::class)->forgetPermission($permission->name);
            event(new PermissionUpdated($permission));
        });

        static::deleted(function (self $permission) {
            app(PermissionCache::class)->forgetPermission($permission->name);
            event(new PermissionDeleted($permission));
        });
    }

    public function getTable(): string
    {
        return Tables::permissions();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            Tables::rolePermissions(),
            'permission_id',
            'role_id'
        )->withPivot('effect');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function findOrCreate(string $name, ?string $guard = null, array $attributes = []): self
    {
        $guard ??= config('permission.default_guard', 'web');

        $permission = static::query()
            ->where('guard_name', $guard)
            ->where(fn ($query) => $query->where('name', $name)->orWhere('slug', $name))
            ->first();

        if ($permission) {
            return $permission;
        }

        return static::query()->create(array_merge([
            'name' => $name,
            'guard_name' => $guard,
        ], $attributes));
    }

    /**
     * @param  list<string>  $actions
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, self>
     */
    public static function defineResource(string $resource, array $actions, array $attributes = []): Collection
    {
        $guard = $attributes['guard'] ?? config('permission.default_guard', 'web');
        $group = $attributes['group'] ?? Str::headline($resource);
        unset($attributes['guard'], $attributes['group']);

        return collect($actions)
            ->filter()
            ->unique()
            ->values()
            ->map(function (string $action) use ($resource, $guard, $group, $attributes) {
                $name = "{$resource}.{$action}";

                $permission = static::findOrCreate($name, $guard, array_merge([
                    'resource' => $resource,
                    'action' => $action,
                    'group' => $group,
                ], $attributes));

                $dirty = false;

                foreach (array_merge([
                    'resource' => $resource,
                    'action' => $action,
                    'group' => $group,
                ], $attributes) as $key => $value) {
                    if ($permission->{$key} !== $value && $value !== null) {
                        $permission->{$key} = $value;
                        $dirty = true;
                    }
                }

                if ($dirty) {
                    $permission->save();
                }

                return $permission;
            });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, self>
     */
    public static function crud(string $resource, array $attributes = []): Collection
    {
        return static::defineResource($resource, ['view', 'create', 'update', 'delete'], $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function define(string $name, array $attributes = []): PermissionDefinition
    {
        $guard = $attributes['guard'] ?? null;
        unset($attributes['guard']);

        return new PermissionDefinition(static::findOrCreate($name, $guard, $attributes));
    }

    /**
     * @return Collection<int, self>
     */
    public static function inGroup(string $group, ?string $guard = null): Collection
    {
        $guard ??= config('permission.default_guard', 'web');

        return static::query()
            ->where('guard_name', $guard)
            ->where('group', $group)
            ->orderBy('name')
            ->get();
    }

    public static function fake(): PermissionFake
    {
        return PermissionFake::activate();
    }

    public static function allow(string $permission): void
    {
        if (! PermissionFake::isActive()) {
            throw new \RuntimeException('Call Permission::fake() before Permission::allow().');
        }

        PermissionFake::instance()->allow($permission);
    }

    public static function deny(string $permission): void
    {
        if (! PermissionFake::isActive()) {
            throw new \RuntimeException('Call Permission::fake() before Permission::deny().');
        }

        PermissionFake::instance()->deny($permission);
    }
}
