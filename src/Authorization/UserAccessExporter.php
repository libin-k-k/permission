<?php

namespace Libinkk\Permission\Authorization;

use Illuminate\Support\Collection;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Permissions\PermissionResolver;
use Libinkk\Permission\Support\WildcardMatcher;

class UserAccessExporter
{
    public function __construct(
        protected PermissionResolver $resolver,
    ) {
    }

    /**
     * Full dump of a user's roles and permissions — totals, sources, groups, resources.
     *
     * @return array<string, mixed>
     */
    public function export(object $user, ?string $guard = null): array
    {
        $guard ??= $this->guardFor($user);
        $roles = $this->resolver->rolesFor($user, $guard);
        $map = $this->resolver->permissionMapFor($user, $guard);
        $registry = $this->resolver->registry($guard);

        $roleDetails = [];
        $rolePermissionCount = 0;

        foreach ($roles as $role) {
            $names = $this->resolver->permissionNamesForRole($role);
            $rolePermissionCount += count($names);
            $roleDetails[] = [
                'id' => $role['id'],
                'name' => $role['name'],
                'slug' => $role['slug'],
                'priority' => (int) ($role['priority'] ?? 0),
                'permissions' => array_values($names),
                'permission_count' => count($names),
            ];
        }

        $direct = [];
        foreach ($map as $name => $entry) {
            if (($entry['source'] ?? null) === 'direct') {
                $direct[] = $name;
            }
        }
        sort($direct);

        $effective = $this->expandEffective($map, $registry);
        $assigned = array_keys($map);
        sort($assigned);

        $byGroup = $this->groupPermissions(array_keys($effective), $registry, 'group');
        $byResource = $this->groupPermissions(array_keys($effective), $registry, 'resource');

        return [
            'user' => [
                'id' => method_exists($user, 'getKey') ? $user->getKey() : null,
                'type' => method_exists($user, 'getMorphClass') ? $user->getMorphClass() : $user::class,
            ],
            'guard' => $guard,
            'exported_at' => now()->toIso8601String(),
            'roles' => $roleDetails,
            'direct_permissions' => $direct,
            'assigned_permissions' => $assigned,
            'effective_permissions' => $effective,
            'by_group' => $byGroup,
            'by_resource' => $byResource,
            'totals' => [
                'roles' => count($roleDetails),
                'direct_permissions' => count($direct),
                'assigned_permissions' => count($assigned),
                'role_permissions' => $rolePermissionCount,
                'effective_permissions' => count($effective),
                'groups' => count($byGroup),
                'resources' => count($byResource),
            ],
        ];
    }

    public function toJson(object $user, ?string $guard = null, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        return (string) json_encode($this->export($user, $guard), $flags);
    }

    /**
     * @param  array<string, array{source: string, role: ?string}>  $map
     * @param  array<string, array<string, mixed>>  $registry
     * @return array<string, array<string, mixed>>
     */
    protected function expandEffective(array $map, array $registry): array
    {
        $effective = [];

        foreach ($map as $name => $entry) {
            if (! WildcardMatcher::isWildcard($name)) {
                $effective[$name] = [
                    'source' => $entry['source'],
                    'role' => $entry['role'] ?? null,
                    'via' => 'exact',
                    'matched' => $name,
                    'group' => $registry[$name]['group'] ?? null,
                    'resource' => $registry[$name]['resource'] ?? $this->resourceFromName($name),
                ];

                continue;
            }

            $effective[$name] = [
                'source' => $entry['source'],
                'role' => $entry['role'] ?? null,
                'via' => 'wildcard',
                'matched' => $name,
                'group' => $registry[$name]['group'] ?? null,
                'resource' => $registry[$name]['resource'] ?? $this->resourceFromName($name),
            ];

            foreach (array_keys($registry) as $registered) {
                if (WildcardMatcher::isWildcard($registered)) {
                    continue;
                }

                if (! WildcardMatcher::matches($name, $registered)) {
                    continue;
                }

                if (isset($effective[$registered]) && ($effective[$registered]['via'] ?? null) === 'exact') {
                    continue;
                }

                $effective[$registered] = [
                    'source' => $entry['source'],
                    'role' => $entry['role'] ?? null,
                    'via' => 'wildcard:'.$name,
                    'matched' => $name,
                    'group' => $registry[$registered]['group'] ?? null,
                    'resource' => $registry[$registered]['resource'] ?? $this->resourceFromName($registered),
                ];
            }
        }

        ksort($effective);

        return $effective;
    }

    /**
     * @param  list<string>  $names
     * @param  array<string, array<string, mixed>>  $registry
     * @return array<string, list<string>>
     */
    protected function groupPermissions(array $names, array $registry, string $field): array
    {
        $grouped = [];

        foreach ($names as $name) {
            if (WildcardMatcher::isWildcard($name) && str_ends_with($name, '.*')) {
                // Keep wildcard keys under their resource bucket.
            }

            $key = $registry[$name][$field]
                ?? ($field === 'resource' ? $this->resourceFromName($name) : 'ungrouped');

            if ($key === null || $key === '') {
                $key = $field === 'resource' ? $this->resourceFromName($name) : 'ungrouped';
            }

            $grouped[(string) $key][] = $name;
        }

        ksort($grouped);

        foreach ($grouped as &$items) {
            sort($items);
        }

        return $grouped;
    }

    protected function resourceFromName(string $name): string
    {
        return str_contains($name, '.') ? explode('.', $name, 2)[0] : $name;
    }

    protected function guardFor(object $user): string
    {
        if (method_exists($user, 'authorizationGuard')) {
            return (string) $user->authorizationGuard();
        }

        if (isset($user->guard_name) && is_string($user->guard_name) && $user->guard_name !== '') {
            return $user->guard_name;
        }

        return (string) config('permission.default_guard', 'web');
    }
}
