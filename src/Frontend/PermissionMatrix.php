<?php

namespace Libinkk\Permission\Frontend;

use Libinkk\Permission\Authorization\Precedence;
use Libinkk\Permission\Permissions\PermissionResolver;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Roles\RoleHierarchy;
use Libinkk\Permission\Support\WildcardMatcher;

class PermissionMatrix
{
    public function __construct(
        protected PermissionResolver $resolver,
        protected RoleHierarchy $hierarchy,
    ) {
    }

    /**
     * @return array{roles: array<string, array<string, array<string, bool>>>}
     */
    public function all(?string $guard = null): array
    {
        $guard ??= (string) config('permission.default_guard', 'web');
        $registry = $this->resolver->registry($guard);
        $roles = [];

        foreach (Role::query()->where('guard_name', $guard)->where('is_active', true)->orderBy('slug')->get() as $role) {
            $roles[$role->slug] = $this->forRole([
                'id' => $role->getKey(),
                'slug' => $role->slug,
                'name' => $role->name,
            ], $registry);
        }

        return ['roles' => $roles];
    }

    /**
     * @param  array{id: mixed, slug: string, name?: string}  $role
     * @param  array<string, array<string, mixed>>  $registry
     * @return array<string, array<string, bool>>
     */
    public function forRole(array $role, array $registry): array
    {
        $map = [];

        foreach ($this->resolver->permissionEntriesForRole($role) as $entry) {
            $this->put($map, $entry['name'], $entry['effect']);
        }

        foreach ($this->hierarchy->inheritedRoles($role) as $inherited) {
            foreach ($this->resolver->permissionEntriesForRole($inherited) as $entry) {
                $this->put($map, $entry['name'], $entry['effect'], inherited: true);
            }
        }

        $resources = [];

        foreach ($registry as $name => $meta) {
            if (WildcardMatcher::isWildcard($name)) {
                continue;
            }

            $resource = $meta['resource'] ?? $this->resourceFromName($name);
            $action = $meta['action'] ?? $this->actionFromName($name);
            $resources[$resource][$action] = $this->allows($map, $name);
        }

        ksort($resources);

        foreach ($resources as &$actions) {
            ksort($actions);
        }

        return $resources;
    }

    /**
     * @param  array<string, array{effect: string, layer: string}>  $map
     */
    protected function put(array &$map, string $name, string $effect, bool $inherited = false): void
    {
        $layer = Precedence::layerFor($effect, $inherited ? 'inherited' : 'role');
        $entry = ['effect' => $effect, 'layer' => $layer];

        if (! isset($map[$name]) || Precedence::rank($layer) < Precedence::rank($map[$name]['layer'])) {
            $map[$name] = $entry;
        }
    }

    /**
     * @param  array<string, array{effect: string, layer: string}>  $map
     */
    protected function allows(array $map, string $permission): bool
    {
        $matches = [];

        if (isset($map[$permission])) {
            $matches[] = $map[$permission];
        }

        foreach ($map as $pattern => $entry) {
            if ($pattern === $permission) {
                continue;
            }

            if (WildcardMatcher::matches((string) $pattern, $permission)) {
                $matches[] = $entry;
            }
        }

        if ($matches === []) {
            return false;
        }

        usort($matches, fn (array $a, array $b) => Precedence::rank($a['layer']) <=> Precedence::rank($b['layer']));

        $winner = $matches[0];

        return ! Precedence::isDeny($winner['layer']) && ($winner['effect'] ?? 'allow') !== 'deny';
    }

    protected function resourceFromName(string $name): string
    {
        return str_contains($name, '.') ? explode('.', $name, 2)[0] : $name;
    }

    protected function actionFromName(string $name): string
    {
        return str_contains($name, '.') ? explode('.', $name, 2)[1] : $name;
    }
}
