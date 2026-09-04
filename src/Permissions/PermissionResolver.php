<?php

namespace Libinkk\Permission\Permissions;

use Libinkk\Permission\Authorization\Precedence;
use Libinkk\Permission\Contracts\PermissionCache;
use Libinkk\Permission\Contracts\PermissionRepository;
use Libinkk\Permission\Contracts\RoleRepository;
use Libinkk\Permission\Roles\RoleHierarchy;
use Libinkk\Permission\Support\WildcardMatcher;

class PermissionResolver
{
    public function __construct(
        protected PermissionRepository $permissions,
        protected RoleRepository $roles,
        protected PermissionCache $cache,
        protected RoleHierarchy $hierarchy,
    ) {
    }

    /**
     * @return array<string, array{effect: string, source: string, role: ?string, layer: string}>
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
                    foreach ($this->permissionEntriesForRole($role) as $entry) {
                        $this->putMap($map, $entry['name'], [
                            'effect' => $entry['effect'],
                            'source' => 'role:'.$role['slug'],
                            'role' => $role['slug'],
                            'layer' => Precedence::layerFor($entry['effect'], 'role'),
                        ]);
                    }

                    foreach ($this->hierarchy->inheritedRoles($role) as $inherited) {
                        foreach ($this->permissionEntriesForRole($inherited) as $entry) {
                            $this->putMap($map, $entry['name'], [
                                'effect' => $entry['effect'],
                                'source' => 'inherited:'.$inherited['slug'],
                                'role' => $inherited['slug'],
                                'layer' => Precedence::layerFor($entry['effect'], 'inherited'),
                            ]);
                        }
                    }
                }

                foreach ($this->permissions->directPermissionEntriesFor($user, $guard) as $entry) {
                    $this->putMap($map, $entry['name'], [
                        'effect' => $entry['effect'],
                        'source' => 'direct',
                        'role' => $map[$entry['name']]['role'] ?? null,
                        'layer' => Precedence::layerFor($entry['effect'], 'direct'),
                    ]);
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
        return collect($this->permissionEntriesForRole($role))
            ->where('effect', 'allow')
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $role
     * @return list<array{name: string, effect: string}>
     */
    public function permissionEntriesForRole(array $role): array
    {
        $slug = (string) $role['slug'];

        return $this->cache->remember(
            "role:{$slug}:permissions",
            'roles',
            fn () => $this->roles->permissionEntriesForRole($role['id'])
        );
    }

    public function isRegistered(string $name, string $guard): bool
    {
        return isset($this->registry($guard)[$name]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function registry(string $guard): array
    {
        return $this->cache->remember(
            "registry:{$guard}",
            'permissions',
            fn () => $this->permissions->allActive($guard)
        );
    }

    /**
     * Resolve an ability against an assigned permission map (exact, then wildcards) using precedence.
     *
     * @param  array<string, array{effect: string, source: string, role: ?string, layer: string}>  $map
     * @return array{effect: string, source: string, role: ?string, layer: string, matched: string, via: string}|null
     */
    public function matchPermission(array $map, string $permission): ?array
    {
        $matches = [];

        if (isset($map[$permission])) {
            $matches[] = $map[$permission] + [
                'matched' => $permission,
                'via' => 'exact',
            ];
        }

        foreach ($map as $pattern => $entry) {
            if ($pattern === $permission) {
                continue;
            }

            if (! WildcardMatcher::matches((string) $pattern, $permission)) {
                continue;
            }

            $matches[] = $entry + [
                'matched' => (string) $pattern,
                'via' => 'wildcard:'.$pattern,
            ];
        }

        if ($matches === []) {
            return null;
        }

        usort($matches, function (array $a, array $b) {
            $rank = Precedence::rank($a['layer']) <=> Precedence::rank($b['layer']);
            if ($rank !== 0) {
                return $rank;
            }

            // Prefer exact over wildcard when same layer.
            $aExact = ($a['via'] ?? '') === 'exact' ? 0 : 1;
            $bExact = ($b['via'] ?? '') === 'exact' ? 0 : 1;

            return $aExact <=> $bExact;
        });

        return $matches[0];
    }

    public function permissionMapCacheSalt(object $user, string $guard): string
    {
        return $this->cache->generations($user).':'.$guard;
    }

    /**
     * @param  array<string, array{effect: string, source: string, role: ?string, layer: string}>  $map
     * @param  array{effect: string, source: string, role: ?string, layer: string}  $entry
     */
    protected function putMap(array &$map, string $name, array $entry): void
    {
        if (! isset($map[$name])) {
            $map[$name] = $entry;

            return;
        }

        if (Precedence::rank($entry['layer']) < Precedence::rank($map[$name]['layer'])) {
            $map[$name] = $entry;
        }
    }
}
