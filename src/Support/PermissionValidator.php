<?php

namespace Libinkk\Permission\Support;

use Illuminate\Support\Facades\DB;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Support\Tables;

class PermissionValidator
{
    /**
     * @return array{ok: bool, errors: list<array<string, mixed>>, warnings: list<array<string, mixed>>}
     */
    public function validate(?string $guard = null): array
    {
        $errors = [];
        $warnings = [];

        $errors = array_merge($errors, $this->duplicatePermissions($guard));
        $errors = array_merge($errors, $this->duplicateRoles($guard));
        $errors = array_merge($errors, $this->orphanAssignments());
        $warnings = array_merge($warnings, $this->inactiveAssigned());
        $warnings = array_merge($warnings, $this->conflictingEffects());

        if (! config('permission.hierarchy.enabled')) {
            $warnings[] = [
                'code' => 'HIERARCHY_DISABLED',
                'message' => 'Role hierarchy validation skipped (permission.hierarchy.enabled=false).',
            ];
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function duplicatePermissions(?string $guard): array
    {
        $query = Permission::query()
            ->select('name', 'guard_name', DB::raw('count(*) as aggregate'))
            ->groupBy('name', 'guard_name')
            ->having('aggregate', '>', 1);

        if ($guard) {
            $query->where('guard_name', $guard);
        }

        return $query->get()->map(fn ($row) => [
            'code' => 'DUPLICATE_PERMISSION',
            'message' => "Duplicate permission [{$row->name}] for guard [{$row->guard_name}].",
            'name' => $row->name,
            'guard' => $row->guard_name,
            'count' => (int) $row->aggregate,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function duplicateRoles(?string $guard): array
    {
        $query = Role::query()
            ->select('name', 'guard_name', 'scope_type', 'scope_id', DB::raw('count(*) as aggregate'))
            ->groupBy('name', 'guard_name', 'scope_type', 'scope_id')
            ->having('aggregate', '>', 1);

        if ($guard) {
            $query->where('guard_name', $guard);
        }

        return $query->get()->map(fn ($row) => [
            'code' => 'DUPLICATE_ROLE',
            'message' => "Duplicate role [{$row->name}] for guard [{$row->guard_name}].",
            'name' => $row->name,
            'guard' => $row->guard_name,
            'count' => (int) $row->aggregate,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function orphanAssignments(): array
    {
        $issues = [];
        $roles = Tables::roles();
        $permissions = Tables::permissions();
        $userRoles = Tables::userRoles();
        $userPermissions = Tables::userPermissions();
        $rolePermissions = Tables::rolePermissions();

        $orphanUserRoles = DB::table($userRoles)
            ->leftJoin($roles, "{$roles}.id", '=', "{$userRoles}.role_id")
            ->whereNull("{$roles}.id")
            ->count();

        if ($orphanUserRoles > 0) {
            $issues[] = [
                'code' => 'ORPHAN_USER_ROLES',
                'message' => "{$orphanUserRoles} user_roles rows reference missing roles.",
                'count' => $orphanUserRoles,
            ];
        }

        $orphanUserPermissions = DB::table($userPermissions)
            ->leftJoin($permissions, "{$permissions}.id", '=', "{$userPermissions}.permission_id")
            ->whereNull("{$permissions}.id")
            ->count();

        if ($orphanUserPermissions > 0) {
            $issues[] = [
                'code' => 'ORPHAN_USER_PERMISSIONS',
                'message' => "{$orphanUserPermissions} user_permissions rows reference missing permissions.",
                'count' => $orphanUserPermissions,
            ];
        }

        $orphanRolePermissions = DB::table($rolePermissions)
            ->leftJoin($permissions, "{$permissions}.id", '=', "{$rolePermissions}.permission_id")
            ->whereNull("{$permissions}.id")
            ->count();

        if ($orphanRolePermissions > 0) {
            $issues[] = [
                'code' => 'ORPHAN_ROLE_PERMISSIONS',
                'message' => "{$orphanRolePermissions} role_permissions rows reference missing permissions.",
                'count' => $orphanRolePermissions,
            ];
        }

        return $issues;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function inactiveAssigned(): array
    {
        $warnings = [];
        $permissions = Tables::permissions();
        $userPermissions = Tables::userPermissions();
        $roles = Tables::roles();
        $userRoles = Tables::userRoles();

        $inactivePerms = DB::table($userPermissions)
            ->join($permissions, "{$permissions}.id", '=', "{$userPermissions}.permission_id")
            ->where("{$permissions}.is_active", false)
            ->count();

        if ($inactivePerms > 0) {
            $warnings[] = [
                'code' => 'INACTIVE_PERMISSION_ASSIGNED',
                'message' => "{$inactivePerms} assignments reference inactive permissions.",
                'count' => $inactivePerms,
            ];
        }

        $inactiveRoles = DB::table($userRoles)
            ->join($roles, "{$roles}.id", '=', "{$userRoles}.role_id")
            ->where("{$roles}.is_active", false)
            ->count();

        if ($inactiveRoles > 0) {
            $warnings[] = [
                'code' => 'INACTIVE_ROLE_ASSIGNED',
                'message' => "{$inactiveRoles} assignments reference inactive roles.",
                'count' => $inactiveRoles,
            ];
        }

        return $warnings;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function conflictingEffects(): array
    {
        $rolePermissions = Tables::rolePermissions();

        $conflicts = DB::table($rolePermissions)
            ->select('role_id', 'permission_id', DB::raw('count(distinct effect) as effects'))
            ->groupBy('role_id', 'permission_id')
            ->having('effects', '>', 1)
            ->count();

        if ($conflicts === 0) {
            return [];
        }

        return [[
            'code' => 'CONFLICTING_EFFECTS',
            'message' => "{$conflicts} role/permission pairs have conflicting allow/deny effects.",
            'count' => $conflicts,
        ]];
    }
}
