<?php

namespace Libinkk\Permission\Cache;

use Libinkk\Permission\Contracts\PermissionCache as PermissionCacheContract;
use Libinkk\Permission\Permissions\PermissionResolver;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Roles\RoleHierarchy;

class PermissionPreloader
{
    public function __construct(
        protected PermissionCacheContract $cache,
        protected PermissionResolver $resolver,
        protected RoleHierarchy $hierarchy,
    ) {
    }

    /**
     * Warm registry, role permissions, and hierarchy. Does not warm every user.
     *
     * @return array{guard: string, permissions: int, roles: int}
     */
    public function warmGuard(?string $guard = null): array
    {
        $guard ??= (string) config('permission.default_guard', 'web');

        $registry = $this->resolver->registry($guard);
        $this->cache->put("registry:{$guard}", $registry, 'permissions');

        $roles = Role::query()
            ->where('guard_name', $guard)
            ->where('is_active', true)
            ->get(['id', 'name', 'slug', 'priority']);

        foreach ($roles as $role) {
            $payload = [
                'id' => $role->getKey(),
                'name' => $role->name,
                'slug' => $role->slug,
                'priority' => (int) $role->priority,
            ];

            $this->resolver->permissionEntriesForRole($payload);
            $this->hierarchy->inheritedRoles($payload);
        }

        return [
            'guard' => $guard,
            'permissions' => count($registry),
            'roles' => $roles->count(),
        ];
    }

    /**
     * Load one user's roles and permission map into L1 (and L2 when persistent).
     *
     * @return array{roles: int, permissions: int}
     */
    public function warmUser(object $user, ?string $guard = null): array
    {
        $guard ??= $this->guardFor($user);
        $roles = $this->resolver->rolesFor($user, $guard);
        $map = $this->resolver->permissionMapFor($user, $guard);

        return [
            'roles' => count($roles),
            'permissions' => count($map),
        ];
    }

    protected function guardFor(object $user): string
    {
        if (method_exists($user, 'authorizationGuard')) {
            return (string) $user->authorizationGuard();
        }

        return (string) config('permission.default_guard', 'web');
    }
}
