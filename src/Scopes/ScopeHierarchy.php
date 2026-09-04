<?php

namespace Libinkk\Permission\Scopes;

use Illuminate\Support\Facades\DB;
use Libinkk\Permission\Contracts\PermissionCache;
use Libinkk\Permission\Exceptions\CircularScopeHierarchyException;
use Libinkk\Permission\Support\Tables;

class ScopeHierarchy
{
    public function __construct(
        protected PermissionCache $cache,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config('permission.scopes.inherit', true);
    }

    public function setParent(Scope $child, Scope $parent): void
    {
        if ((string) $child->getKey() === (string) $parent->getKey()) {
            throw new CircularScopeHierarchyException('A scope cannot be its own parent.');
        }

        if ($this->wouldCreateCycle($child, $parent)) {
            throw new CircularScopeHierarchyException(
                "Circular scope hierarchy: [{$parent->name}] cannot parent [{$child->name}]."
            );
        }

        $child->parent_id = $parent->getKey();
        $child->save();
        $this->cache->forgetScope($child->getKey());
        $this->cache->forgetScope($parent->getKey());
    }

    /**
     * Ancestors from nearest parent up to root (does not include $scope).
     *
     * @return list<Scope>
     */
    public function ancestors(Scope $scope): array
    {
        $found = [];
        $current = $scope->parent_id;
        $guard = 0;

        while ($current && $guard++ < 50) {
            $parent = Scope::query()->find($current);
            if (! $parent) {
                break;
            }
            $found[] = $parent;
            $current = $parent->parent_id;
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    public function ancestorIdentities(Scope $scope): array
    {
        return array_map(fn (Scope $ancestor) => $ancestor->identity(), $this->ancestors($scope));
    }

    public function wouldCreateCycle(Scope $child, Scope $parent): bool
    {
        // Parenting child under parent is a cycle if child is already an ancestor of parent.
        $current = $parent->parent_id;
        $guard = 0;

        while ($current && $guard++ < 50) {
            if ((string) $current === (string) $child->getKey()) {
                return true;
            }
            $row = DB::table(Tables::get('scopes', 'scopes'))->where('id', $current)->first();
            $current = $row->parent_id ?? null;
        }

        return false;
    }
}
