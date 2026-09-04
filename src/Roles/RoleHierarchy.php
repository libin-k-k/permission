<?php

namespace Libinkk\Permission\Roles;

use Illuminate\Support\Facades\DB;
use Libinkk\Permission\Contracts\PermissionCache;
use Libinkk\Permission\Exceptions\CircularRoleInheritanceException;
use Libinkk\Permission\Support\Tables;

class RoleHierarchy
{
    public function __construct(
        protected PermissionCache $cache,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config('permission.hierarchy.enabled', true);
    }

    /**
     * Make $parent inherit permissions from $child (and its descendants).
     */
    public function inherit(string|Role $parent, string|Role $child, ?string $guard = null): void
    {
        $guard ??= config('permission.default_guard', 'web');
        $parentRole = $parent instanceof Role ? $parent : Role::findOrCreate($parent, $guard);
        $childRole = $child instanceof Role ? $child : Role::findOrCreate($child, $guard);

        if ((string) $parentRole->getKey() === (string) $childRole->getKey()) {
            throw CircularRoleInheritanceException::for($parentRole->slug, $childRole->slug);
        }

        if ($this->wouldCreateCycle($parentRole, $childRole)) {
            throw CircularRoleInheritanceException::for($parentRole->slug, $childRole->slug);
        }

        DB::table(Tables::roleInheritances())->insertOrIgnore([
            'parent_role_id' => $parentRole->getKey(),
            'child_role_id' => $childRole->getKey(),
            'created_at' => now(),
        ]);

        $this->forget($parentRole->slug);
        $this->forget($childRole->slug);
    }

    public function uninherit(string|Role $parent, string|Role $child, ?string $guard = null): void
    {
        $guard ??= config('permission.default_guard', 'web');
        $parentRole = $parent instanceof Role ? $parent : Role::query()
            ->where('guard_name', $guard)
            ->where(fn ($q) => $q->where('slug', $parent)->orWhere('name', $parent))
            ->first();
        $childRole = $child instanceof Role ? $child : Role::query()
            ->where('guard_name', $guard)
            ->where(fn ($q) => $q->where('slug', $child)->orWhere('name', $child))
            ->first();

        if (! $parentRole || ! $childRole) {
            return;
        }

        DB::table(Tables::roleInheritances())
            ->where('parent_role_id', $parentRole->getKey())
            ->where('child_role_id', $childRole->getKey())
            ->delete();

        $this->forget($parentRole->slug);
        $this->forget($childRole->slug);
    }

    /**
     * Descendants whose permissions are inherited by the given role.
     *
     * @return list<array<string, mixed>>
     */
    public function inheritedRoles(array $role): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $slug = (string) $role['slug'];

        return $this->cache->remember(
            "role:{$slug}:hierarchy",
            'roles',
            function () use ($role) {
                $found = [];
                $queue = [(string) $role['id']];
                $seen = [(string) $role['id'] => true];

                while ($queue !== []) {
                    $current = array_shift($queue);
                    $children = DB::table(Tables::roleInheritances().' as ri')
                        ->join(Tables::roles().' as r', 'r.id', '=', 'ri.child_role_id')
                        ->where('ri.parent_role_id', $current)
                        ->where('r.is_active', true)
                        ->whereNull('r.deleted_at')
                        ->get(['r.id', 'r.name', 'r.slug', 'r.priority']);

                    foreach ($children as $child) {
                        $id = (string) $child->id;
                        if (isset($seen[$id])) {
                            continue;
                        }
                        $seen[$id] = true;
                        $found[] = [
                            'id' => $child->id,
                            'name' => $child->name,
                            'slug' => $child->slug,
                            'priority' => (int) $child->priority,
                        ];
                        $queue[] = $id;
                    }
                }

                return $found;
            }
        );
    }

    public function wouldCreateCycle(Role $parent, Role $child): bool
    {
        // Adding parent → child is a cycle if parent is already reachable from child.
        $queue = [(string) $child->getKey()];
        $seen = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            if ($current === (string) $parent->getKey()) {
                return true;
            }
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;

            $next = DB::table(Tables::roleInheritances())
                ->where('parent_role_id', $current)
                ->pluck('child_role_id')
                ->all();

            foreach ($next as $id) {
                $queue[] = (string) $id;
            }
        }

        return false;
    }

    /**
     * @return list<array{parent: string, child: string}>
     */
    public function detectCycles(?string $guard = null): array
    {
        $roles = Role::query()
            ->when($guard, fn ($q) => $q->where('guard_name', $guard))
            ->get(['id', 'slug']);

        $edges = DB::table(Tables::roleInheritances())->get(['parent_role_id', 'child_role_id']);
        $graph = [];
        foreach ($edges as $edge) {
            $graph[(string) $edge->parent_role_id][] = (string) $edge->child_role_id;
        }

        $slugById = $roles->mapWithKeys(fn (Role $role) => [(string) $role->getKey() => $role->slug])->all();
        $cycles = [];

        foreach ($roles as $role) {
            $id = (string) $role->getKey();
            $path = [];
            if ($this->dfsCycle($id, $graph, [], $path)) {
                $cycles[] = [
                    'parent' => $slugById[$path[0]] ?? $path[0],
                    'child' => $slugById[$path[count($path) - 1]] ?? $path[count($path) - 1],
                    'path' => array_map(fn ($node) => $slugById[$node] ?? $node, $path),
                ];
            }
        }

        return $cycles;
    }

    /**
     * @param  array<string, list<string>>  $graph
     * @param  array<string, bool>  $stack
     * @param  list<string>  $path
     */
    protected function dfsCycle(string $node, array $graph, array $stack, array &$path): bool
    {
        if (isset($stack[$node])) {
            $path[] = $node;

            return true;
        }

        $stack[$node] = true;
        $path[] = $node;

        foreach ($graph[$node] ?? [] as $next) {
            if ($this->dfsCycle($next, $graph, $stack, $path)) {
                return true;
            }
        }

        array_pop($path);

        return false;
    }

    protected function forget(string $slug): void
    {
        $this->cache->forget("role:{$slug}:hierarchy");
        $this->cache->forgetRole($slug);
    }
}
