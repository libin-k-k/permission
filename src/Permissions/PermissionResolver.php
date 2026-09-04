<?php

namespace Libinkk\Permission\Permissions;

use Libinkk\Permission\Contracts\PermissionCache;
use Libinkk\Permission\Contracts\PermissionRepository;
use Libinkk\Permission\Contracts\RoleRepository;

class PermissionResolver
{
    public function __construct(
        protected PermissionRepository $permissions,
        protected RoleRepository $roles,
        protected PermissionCache $cache,
    ) {
    }

    /**
     * @return array<string, array{source: string, role: ?string}>
     */
    public function permissionMapFor(object $user, string $guard): array
    {
        $userKey = $this->cache->userKey($user);
        $generation = $this->cache->generations($user);

        return $this->cache->remember(
            "user:{$userKey}:permissions:{$generation}",
            'user_permissions',
            function () use ($user, $guard, $userKey, $generation) {
                $map = [];

                foreach ($this->rolesFor($user, $guard, $userKey, $generation) as $role) {
                    foreach ($this->permissionNamesForRole($role) as $name) {
                        if (! isset($map[$name])) {
                            $map[$name] = [
                                'source' => 'role:'.$role['slug'],
                                'role' => $role['slug'],
                            ];
                        }
                    }
                }

                foreach ($this->permissions->directPermissionsFor($user, $guard) as $name) {
                    $map[$name] = [
                        'source' => 'direct',
                        'role' => $map[$name]['role'] ?? null,
                    ];
                }

                return $map;
            }
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rolesFor(object $user, string $guard, ?string $userKey = null, ?string $generation = null): array
    {
        $userKey ??= $this->cache->userKey($user);
        $generation ??= $this->cache->generations($user);

        return $this->cache->remember(
            "user:{$userKey}:roles:{$generation}",
            'user_roles',
            fn () => $this->roles->assignedRoles($user, $guard)
        );
    }

    /**
     * @param  array<string, mixed>  $role
     * @return list<string>
     */
    public function permissionNamesForRole(array $role): array
    {
        $slug = (string) $role['slug'];

        return $this->cache->remember(
            "role:{$slug}:permissions",
            'roles',
            fn () => $this->roles->permissionNamesForRole($role['id'])
        );
    }

    public function isRegistered(string $name, string $guard): bool
    {
        $registry = $this->cache->remember(
            "registry:{$guard}",
            'permissions',
            fn () => $this->permissions->allActive($guard)
        );

        return isset($registry[$name]);
    }

    public function permissionMapCacheSalt(object $user, string $guard): string
    {
        return $this->cache->generations($user).':'.$guard;
    }
}
