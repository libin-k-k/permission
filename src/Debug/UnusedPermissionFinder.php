<?php

namespace Libinkk\Permission\Debug;

use Illuminate\Support\Facades\DB;
use Libinkk\Permission\Discovery\PermissionDiscovery;
use Libinkk\Permission\Support\Tables;

class UnusedPermissionFinder
{
    public function __construct(
        protected PermissionDiscovery $discovery,
    ) {
    }

    /**
     * Permissions that are unused, inactive, missing from code, or assigned to empty roles.
     *
     * @return array{
     *     guard: string,
     *     unassigned: list<string>,
     *     inactive: list<string>,
     *     not_in_code: list<string>,
     *     assigned_without_users: list<string>,
     *     total: int
     * }
     */
    public function find(?string $guard = null): array
    {
        $guard ??= (string) config('permission.default_guard', 'web');
        $permissions = Tables::permissions();
        $rolePermissions = Tables::rolePermissions();
        $userPermissions = Tables::userPermissions();
        $userRoles = Tables::userRoles();

        $rows = DB::table($permissions)
            ->where('guard_name', $guard)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        $assignedToRole = $this->idSet(
            DB::table($rolePermissions)->distinct()->pluck('permission_id')
        );
        $assignedToUser = $this->idSet(
            DB::table($userPermissions)->distinct()->pluck('permission_id')
        );
        $rolesWithUsers = $this->idSet(
            DB::table($userRoles)->distinct()->pluck('role_id')
        );
        $permissionsOnPopulatedRoles = $this->idSet(
            DB::table($rolePermissions)
                ->whereIn('role_id', $rolesWithUsers === [] ? [0] : array_keys($rolesWithUsers))
                ->pluck('permission_id')
        );

        $discovered = $this->discoveredNames();

        $unassigned = [];
        $inactive = [];
        $notInCode = [];
        $withoutUsers = [];

        foreach ($rows as $row) {
            $id = (string) $row->id;
            $name = (string) $row->name;
            $onRole = isset($assignedToRole[$id]);
            $onUser = isset($assignedToUser[$id]);

            if (! $onRole && ! $onUser) {
                $unassigned[] = $name;
            }

            if (! $row->is_active) {
                $inactive[] = $name;
            }

            if ($discovered !== null && ! isset($discovered[$name])) {
                $notInCode[] = $name;
            }

            if (($onRole || $onUser) && ! $onUser && ! isset($permissionsOnPopulatedRoles[$id])) {
                $withoutUsers[] = $name;
            }
        }

        $unique = array_values(array_unique(array_merge($unassigned, $inactive, $notInCode, $withoutUsers)));
        sort($unique);

        return [
            'guard' => $guard,
            'unassigned' => $unassigned,
            'inactive' => $inactive,
            'not_in_code' => $notInCode,
            'assigned_without_users' => $withoutUsers,
            'total' => count($unique),
        ];
    }

    /**
     * @return array<string, true>|null
     */
    protected function discoveredNames(): ?array
    {
        $paths = config('permission.discovery.paths', []);

        if (! is_array($paths) || $paths === []) {
            return null;
        }

        $names = [];
        foreach ($this->discovery->discover($paths) as $item) {
            $names[(string) $item['name']] = true;
        }

        return $names;
    }

    /**
     * @param  iterable<mixed>  $ids
     * @return array<string, true>
     */
    protected function idSet(iterable $ids): array
    {
        $set = [];
        foreach ($ids as $id) {
            $set[(string) $id] = true;
        }

        return $set;
    }
}
