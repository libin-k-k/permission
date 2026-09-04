<?php

namespace Libinkk\Permission\Roles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Libinkk\Permission\Contracts\PermissionCache;
use Libinkk\Permission\Events\RoleCreated;
use Libinkk\Permission\Events\RoleDeleted;
use Libinkk\Permission\Events\RoleUpdated;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\RoleHierarchy;
use Libinkk\Permission\Support\Tables;
use Libinkk\Permission\Support\UsesConfiguredKeys;

class Role extends Model
{
    use SoftDeletes;
    use UsesConfiguredKeys;

    protected $guarded = [];

    protected $casts = [
        'priority' => 'integer',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $role) {
            $role->slug ??= Str::slug((string) $role->name);
            $role->guard_name ??= config('permission.default_guard', 'web');
            $role->scope_type ??= 'global';
            $role->scope_id ??= '';
        });

        static::created(function (self $role) {
            app(PermissionCache::class)->forgetRole($role->slug);
            event(new RoleCreated($role));
        });

        static::updated(function (self $role) {
            app(PermissionCache::class)->forgetRole($role->slug);
            event(new RoleUpdated($role));
        });

        static::deleted(function (self $role) {
            app(PermissionCache::class)->forgetRole($role->slug);
            event(new RoleDeleted($role));
        });
    }

    public function getTable(): string
    {
        return Tables::roles();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            Tables::rolePermissions(),
            'role_id',
            'permission_id'
        )->withPivot('effect');
    }

    public static function findOrCreate(string $name, ?string $guard = null): self
    {
        $guard ??= config('permission.default_guard', 'web');

        $role = static::query()
            ->where('guard_name', $guard)
            ->where(fn ($query) => $query->where('slug', $name)->orWhere('name', $name))
            ->first();

        if ($role) {
            return $role;
        }

        return static::query()->create([
            'name' => $name,
            'guard_name' => $guard,
        ]);
    }

    public function givePermissionTo(string|Permission|array ...$permissions): static
    {
        return $this->syncPermissionEffect($permissions, 'allow');
    }

    public function denyPermissionTo(string|Permission|array ...$permissions): static
    {
        return $this->syncPermissionEffect($permissions, 'deny');
    }

    public function revokePermissionTo(string|Permission|array ...$permissions): static
    {
        $guard = $this->guard_name ?: config('permission.default_guard', 'web');
        $ids = collect($this->normalizePermissions($permissions, $guard))->map->getKey()->all();

        if ($ids !== []) {
            $this->permissions()->detach($ids);
            app(PermissionCache::class)->forgetRole($this->slug);
        }

        return $this;
    }

    public function syncPermissions(string|Permission|array ...$permissions): static
    {
        $guard = $this->guard_name ?: config('permission.default_guard', 'web');
        $sync = [];

        foreach ($this->normalizePermissions($permissions, $guard) as $permission) {
            $sync[$permission->getKey()] = ['effect' => 'allow'];
        }

        $this->permissions()->sync($sync);
        app(PermissionCache::class)->forgetRole($this->slug);

        return $this;
    }

    public static function inherit(string|Role $parent, string|Role $child, ?string $guard = null): void
    {
        app(RoleHierarchy::class)->inherit($parent, $child, $guard);
    }

    public static function uninherit(string|Role $parent, string|Role $child, ?string $guard = null): void
    {
        app(RoleHierarchy::class)->uninherit($parent, $child, $guard);
    }

    public function hasPermissionTo(string|Permission $permission): bool
    {
        $name = $permission instanceof Permission ? $permission->name : $permission;

        return $this->permissions->contains(
            fn (Permission $model) => $model->name === $name && ($model->pivot->effect ?? 'allow') === 'allow'
        );
    }

    /**
     * @param  array<int, string|Permission|array>  $permissions
     */
    protected function syncPermissionEffect(array $permissions, string $effect): static
    {
        $guard = $this->guard_name ?: config('permission.default_guard', 'web');

        foreach ($this->normalizePermissions($permissions, $guard) as $permission) {
            $this->permissions()->syncWithoutDetaching([
                $permission->getKey() => ['effect' => $effect],
            ]);
        }

        app(PermissionCache::class)->forgetRole($this->slug);

        return $this;
    }

    /**
     * @param  array<int, string|Permission|array>  $permissions
     * @return list<Permission>
     */
    protected function normalizePermissions(array $permissions, string $guard): array
    {
        return collect($permissions)
            ->flatten()
            ->filter()
            ->map(function (string|Permission $permission) use ($guard) {
                return $permission instanceof Permission
                    ? $permission
                    : Permission::findOrCreate($permission, $guard);
            })
            ->unique(fn (Permission $permission) => $permission->getKey())
            ->values()
            ->all();
    }
}
