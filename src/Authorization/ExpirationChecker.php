<?php

namespace Libinkk\Permission\Authorization;

use Illuminate\Support\Facades\DB;
use Libinkk\Permission\Scopes\ScopeResolver;
use Libinkk\Permission\Support\Tables;
use Libinkk\Permission\Support\WildcardMatcher;

class ExpirationChecker
{
    public function __construct(
        protected ScopeResolver $scopes,
    ) {
    }

    public function expiredGrantExists(object $user, string $permission, string $guard): bool
    {
        $scopePivots = $this->scopes->teamsEnabled() ? $this->scopes->matchingPivots() : [];

        foreach ($this->expiredDirectNames($user, $guard, $scopePivots) as $name) {
            if ($this->matches($name, $permission)) {
                return true;
            }
        }

        foreach ($this->expiredRolePermissionNames($user, $guard, $scopePivots) as $name) {
            if ($this->matches($name, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{scope_type: string, scope_id: string}>  $scopePivots
     * @return list<string>
     */
    protected function expiredDirectNames(object $user, string $guard, array $scopePivots): array
    {
        $now = now();
        $permissions = Tables::permissions();
        $userPermissions = Tables::userPermissions();

        $query = DB::table($userPermissions)
            ->join($permissions, "{$permissions}.id", '=', "{$userPermissions}.permission_id")
            ->where("{$userPermissions}.user_type", $user->getMorphClass())
            ->where("{$userPermissions}.user_id", $user->getKey())
            ->where("{$permissions}.guard_name", $guard)
            ->whereNull("{$permissions}.deleted_at")
            ->whereNotNull("{$userPermissions}.expires_at")
            ->where("{$userPermissions}.expires_at", '<=', $now)
            ->where(function ($query) use ($userPermissions, $now) {
                $query->whereNull("{$userPermissions}.starts_at")
                    ->orWhere("{$userPermissions}.starts_at", '<=', $now);
            });

        $this->constrainScope($query, $userPermissions, $scopePivots);

        return $query
            ->pluck("{$permissions}.name")
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array{scope_type: string, scope_id: string}>  $scopePivots
     * @return list<string>
     */
    protected function expiredRolePermissionNames(object $user, string $guard, array $scopePivots): array
    {
        $now = now();
        $roles = Tables::roles();
        $userRoles = Tables::userRoles();
        $permissions = Tables::permissions();
        $rolePermissions = Tables::rolePermissions();

        $query = DB::table($userRoles)
            ->join($roles, "{$roles}.id", '=', "{$userRoles}.role_id")
            ->join($rolePermissions, "{$rolePermissions}.role_id", '=', "{$roles}.id")
            ->join($permissions, "{$permissions}.id", '=', "{$rolePermissions}.permission_id")
            ->where("{$userRoles}.user_type", $user->getMorphClass())
            ->where("{$userRoles}.user_id", $user->getKey())
            ->where("{$roles}.guard_name", $guard)
            ->whereNull("{$roles}.deleted_at")
            ->whereNull("{$permissions}.deleted_at")
            ->whereNotNull("{$userRoles}.expires_at")
            ->where("{$userRoles}.expires_at", '<=', $now)
            ->where(function ($query) use ($userRoles, $now) {
                $query->whereNull("{$userRoles}.starts_at")
                    ->orWhere("{$userRoles}.starts_at", '<=', $now);
            });

        $this->constrainScope($query, $userRoles, $scopePivots);

        return $query
            ->pluck("{$permissions}.name")
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  list<array{scope_type: string, scope_id: string}>  $scopePivots
     */
    protected function constrainScope($query, string $table, array $scopePivots): void
    {
        if ($scopePivots === []) {
            return;
        }

        $query->where(function ($outer) use ($table, $scopePivots) {
            foreach ($scopePivots as $index => $pivot) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $outer->{$method}(function ($inner) use ($table, $pivot) {
                    $inner->where("{$table}.scope_type", $pivot['scope_type'])
                        ->where("{$table}.scope_id", $pivot['scope_id']);
                });
            }
        });
    }

    protected function matches(string $granted, string $requested): bool
    {
        return $granted === $requested || WildcardMatcher::matches($granted, $requested);
    }
}
