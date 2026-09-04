<?php

namespace Libinkk\Permission\Scopes;

use Illuminate\Support\Facades\DB;
use Libinkk\Permission\Contracts\PermissionCache;
use Libinkk\Permission\Support\Tables;

class ScopeResolver
{
    public function __construct(
        protected PermissionCache $cache,
        protected ScopeHierarchy $hierarchy,
    ) {
    }

    public function teamsEnabled(): bool
    {
        return (bool) config('permission.teams.enabled', false);
    }

    /**
     * Normalize a tenant, scope, or application model into a stable identity string.
     */
    public function identity(mixed $target): string
    {
        if ($target === null) {
            return 'global:';
        }

        if ($target instanceof Scope) {
            return $target->identity();
        }

        if (is_object($target) && method_exists($target, 'getKey')) {
            $type = method_exists($target, 'getMorphClass') ? $target->getMorphClass() : $target::class;

            return $type.':'.$target->getKey();
        }

        return 'global:';
    }

    public function pivotFor(mixed $target): array
    {
        if ($target === null) {
            return ['scope_type' => 'global', 'scope_id' => ''];
        }

        if ($target instanceof Scope) {
            if ($target->scopeable_type && $target->scopeable_id !== null && $target->scopeable_id !== '') {
                return [
                    'scope_type' => $target->scopeable_type,
                    'scope_id' => (string) $target->scopeable_id,
                ];
            }

            return [
                'scope_type' => 'scope',
                'scope_id' => (string) $target->getKey(),
            ];
        }

        if (is_object($target) && method_exists($target, 'getKey')) {
            $type = method_exists($target, 'getMorphClass') ? $target->getMorphClass() : $target::class;

            return [
                'scope_type' => $type,
                'scope_id' => (string) $target->getKey(),
            ];
        }

        return ['scope_type' => 'global', 'scope_id' => ''];
    }

    /**
     * Identities whose assignments apply in the current context.
     *
     * Parent (ancestor) grants apply to the current child when inheritance is enabled.
     *
     * @return list<array{scope_type: string, scope_id: string}>
     */
    public function applicablePivots(mixed $current): array
    {
        $pivots = [$this->pivotFor($current)];

        if ($current === null) {
            return $pivots;
        }

        if (! $this->hierarchy->enabled()) {
            return $this->uniquePivots($pivots);
        }

        $scope = $this->scopeRecord($current);

        if ($scope) {
            foreach ($this->hierarchy->ancestors($scope) as $ancestor) {
                $pivots[] = $this->pivotFor($ancestor);
            }
        }

        return $this->uniquePivots($pivots);
    }

    /**
     * @return list<array{scope_type: string, scope_id: string}>
     */
    public function matchingPivots(): array
    {
        if (! $this->teamsEnabled()) {
            return [];
        }

        $current = \Libinkk\Permission\Authorization\AuthorizationContext::currentTarget();

        $pivots = $this->applicablePivots($current);

        if ($this->includeGlobal()) {
            $pivots[] = ['scope_type' => 'global', 'scope_id' => ''];
        }

        return $this->uniquePivots($pivots);
    }

    public function includeGlobal(): bool
    {
        $current = \Libinkk\Permission\Authorization\AuthorizationContext::currentTarget();

        if ($current === null) {
            return true;
        }

        return (bool) config('permission.teams.global_roles.cross_tenant', false);
    }

    public function cacheSalt(): string
    {
        if (! $this->teamsEnabled()) {
            return 'global';
        }

        $current = \Libinkk\Permission\Authorization\AuthorizationContext::currentTarget();

        return $this->identity($current).'|g'.($this->includeGlobal() ? '1' : '0');
    }

    public function label(): ?string
    {
        $current = \Libinkk\Permission\Authorization\AuthorizationContext::currentTarget();

        if ($current === null) {
            return $this->teamsEnabled() ? 'global' : null;
        }

        return $this->identity($current);
    }

    public function grantUser(object $user, mixed $target): void
    {
        $scope = $this->scopeRecord($target, create: true);

        if (! $scope) {
            return;
        }

        $userType = method_exists($user, 'getMorphClass') ? $user->getMorphClass() : $user::class;
        $userId = method_exists($user, 'getKey') ? $user->getKey() : null;

        if ($userId === null) {
            return;
        }

        DB::table(Tables::get('user_scopes', 'user_scopes'))->insertOrIgnore([
            'user_type' => $userType,
            'user_id' => $userId,
            'scope_id' => $scope->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($target instanceof Tenant) {
            DB::table(Tables::get('tenant_users', 'tenant_users'))->insertOrIgnore([
                'tenant_id' => $target->getKey(),
                'user_type' => $userType,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function userCanAccess(object $user, mixed $target): bool
    {
        if ($target === null) {
            return true;
        }

        if (! config('permission.teams.require_membership', false)) {
            return true;
        }

        $userType = method_exists($user, 'getMorphClass') ? $user->getMorphClass() : $user::class;
        $userId = method_exists($user, 'getKey') ? $user->getKey() : null;

        if ($userId === null) {
            return false;
        }

        $scopeIds = [];
        $scope = $this->scopeRecord($target);

        if ($scope) {
            $scopeIds[] = $scope->getKey();
            foreach ($this->hierarchy->ancestors($scope) as $ancestor) {
                $scopeIds[] = $ancestor->getKey();
            }
        }

        if ($scopeIds !== []) {
            $member = DB::table(Tables::get('user_scopes', 'user_scopes'))
                ->where('user_type', $userType)
                ->where('user_id', $userId)
                ->whereIn('scope_id', $scopeIds)
                ->exists();

            if ($member) {
                return true;
            }
        }

        $pivot = $this->pivotFor($target);
        $roles = Tables::get('user_roles', 'user_roles');
        $perms = Tables::get('user_permissions', 'user_permissions');

        foreach ($this->applicablePivots($target) as $match) {
            $hasRole = DB::table($roles)
                ->where('user_type', $userType)
                ->where('user_id', $userId)
                ->where('scope_type', $match['scope_type'])
                ->where('scope_id', $match['scope_id'])
                ->exists();

            if ($hasRole) {
                return true;
            }

            $hasPerm = DB::table($perms)
                ->where('user_type', $userType)
                ->where('user_id', $userId)
                ->where('scope_type', $match['scope_type'])
                ->where('scope_id', $match['scope_id'])
                ->exists();

            if ($hasPerm) {
                return true;
            }
        }

        return false;
    }

    public function userHasOtherTenantAssignments(object $user, mixed $target): bool
    {
        $userType = method_exists($user, 'getMorphClass') ? $user->getMorphClass() : $user::class;
        $userId = method_exists($user, 'getKey') ? $user->getKey() : null;

        if ($userId === null || $target === null) {
            return false;
        }

        $allowed = collect($this->applicablePivots($target))
            ->map(fn (array $pivot) => $pivot['scope_type'].'|'.$pivot['scope_id'])
            ->all();

        $rows = DB::table(Tables::get('user_roles', 'user_roles'))
            ->where('user_type', $userType)
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query->where('scope_type', '!=', 'global')
                    ->orWhere('scope_id', '!=', '');
            })
            ->get(['scope_type', 'scope_id']);

        foreach ($rows as $row) {
            $key = $row->scope_type.'|'.$row->scope_id;
            if (! in_array($key, $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    public function isNestedScope(mixed $target): bool
    {
        $scope = $this->scopeRecord($target);

        return $scope !== null && $scope->parent_id !== null;
    }

    public function scopeRecord(mixed $target, bool $create = false): ?Scope
    {
        if ($target instanceof Scope) {
            return $target;
        }

        if (! is_object($target) || ! method_exists($target, 'getKey')) {
            return null;
        }

        if ($create) {
            $parent = null;
            if (isset($target->parent) && is_object($target->parent)) {
                $parent = $this->scopeRecord($target->parent, create: true);
            }

            return Scope::for($target, parent: $parent);
        }

        $type = method_exists($target, 'getMorphClass') ? $target->getMorphClass() : $target::class;

        return Scope::query()
            ->where('scopeable_type', $type)
            ->where('scopeable_id', (string) $target->getKey())
            ->first();
    }

    /**
     * @param  list<array{scope_type: string, scope_id: string}>  $pivots
     * @return list<array{scope_type: string, scope_id: string}>
     */
    protected function uniquePivots(array $pivots): array
    {
        $seen = [];
        $out = [];

        foreach ($pivots as $pivot) {
            $key = $pivot['scope_type'].'|'.$pivot['scope_id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $pivot;
        }

        return $out;
    }
}
