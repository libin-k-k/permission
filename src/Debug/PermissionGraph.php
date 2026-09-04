<?php

namespace Libinkk\Permission\Debug;

use Illuminate\Support\Facades\DB;
use Libinkk\Permission\Permissions\PermissionResolver;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Support\Tables;

class PermissionGraph
{
    public function __construct(
        protected PermissionResolver $resolver,
    ) {
    }

    /**
     * Role inheritance tree plus permissions assigned to each role.
     *
     * @return array{guard: string, hierarchy: list<array<string, mixed>>, permissions: array<string, list<string>>}
     */
    public function build(?string $guard = null): array
    {
        $guard ??= (string) config('permission.default_guard', 'web');

        $roles = Role::query()
            ->where('guard_name', $guard)
            ->orderBy('priority')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'priority', 'is_active']);

        $edges = DB::table(Tables::roleInheritances())->get(['parent_role_id', 'child_role_id']);
        $children = [];

        foreach ($edges as $edge) {
            $children[(string) $edge->parent_role_id][] = (string) $edge->child_role_id;
        }

        $byId = [];
        foreach ($roles as $role) {
            $byId[(string) $role->getKey()] = $role;
        }

        $childIds = [];
        foreach ($children as $kids) {
            foreach ($kids as $id) {
                $childIds[$id] = true;
            }
        }

        $hierarchy = [];
        foreach ($roles as $role) {
            $id = (string) $role->getKey();
            if (isset($childIds[$id])) {
                continue;
            }

            $hierarchy[] = $this->node($role, $children, $byId, []);
        }

        $permissions = [];
        foreach ($roles as $role) {
            $permissions[(string) $role->slug] = $this->resolver->permissionNamesForRole([
                'id' => $role->getKey(),
                'slug' => $role->slug,
                'name' => $role->name,
            ]);
            sort($permissions[(string) $role->slug]);
        }

        return [
            'guard' => $guard,
            'hierarchy' => $hierarchy,
            'permissions' => $permissions,
        ];
    }

    /**
     * @param  array<string, mixed>  $graph
     */
    public function toText(array $graph): string
    {
        $lines = [];

        foreach ($graph['hierarchy'] as $root) {
            $lines[] = $this->renderNode($root, '', true, true);
            $lines[] = '';
        }

        $lines[] = 'Permission graph:';
        $lines[] = '';

        foreach ($graph['permissions'] as $slug => $names) {
            $role = $this->findRoleName($graph['hierarchy'], (string) $slug) ?? $slug;
            $lines[] = $role;

            if ($names === []) {
                $lines[] = ' └── (none)';
                $lines[] = '';

                continue;
            }

            foreach (array_values($names) as $index => $name) {
                $last = $index === count($names) - 1;
                $lines[] = ($last ? ' └── ' : ' ├── ').$name;
            }

            $lines[] = '';
        }

        return trim(implode(PHP_EOL, $lines)).PHP_EOL;
    }

    /**
     * @param  array<string, mixed>  $graph
     */
    public function toJson(array $graph, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        return (string) json_encode($graph, $flags);
    }

    /**
     * @param  array<string, list<string>>  $children
     * @param  array<string, Role>  $byId
     * @param  array<string, bool>  $stack
     * @return array<string, mixed>
     */
    protected function node(Role $role, array $children, array $byId, array $stack): array
    {
        $id = (string) $role->getKey();
        $kids = [];

        if (! isset($stack[$id])) {
            $stack[$id] = true;
            foreach ($children[$id] ?? [] as $childId) {
                if (! isset($byId[$childId])) {
                    continue;
                }
                $kids[] = $this->node($byId[$childId], $children, $byId, $stack);
            }
        }

        return [
            'id' => $role->getKey(),
            'name' => $role->name,
            'slug' => $role->slug,
            'priority' => (int) $role->priority,
            'is_active' => (bool) $role->is_active,
            'children' => $kids,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function renderNode(array $node, string $prefix, bool $isLast, bool $isRoot): string
    {
        if ($isRoot) {
            $line = (string) $node['name'];
            $childPrefix = '';
        } else {
            $line = $prefix.($isLast ? ' └── ' : ' ├── ').(string) $node['name'];
            $childPrefix = $prefix.($isLast ? '     ' : ' │    ');
        }

        $lines = [$line];
        $children = $node['children'] ?? [];

        foreach ($children as $index => $child) {
            $last = $index === array_key_last($children);
            $lines[] = $this->renderNode($child, $childPrefix, $last, false);
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    protected function findRoleName(array $nodes, string $slug): ?string
    {
        foreach ($nodes as $node) {
            if (($node['slug'] ?? null) === $slug) {
                return (string) $node['name'];
            }

            $nested = $this->findRoleName($node['children'] ?? [], $slug);
            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }
}
