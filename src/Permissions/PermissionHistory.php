<?php

namespace Libinkk\Permission\Permissions;

use Illuminate\Support\Collection;
use Libinkk\Permission\Audit\AuthorizationAudit;

class PermissionHistory
{
    /**
     * Version snapshots plus related assignment / policy audit rows.
     *
     * @return array{permission: string, versions: list<array<string, mixed>>, audits: list<array<string, mixed>>}
     */
    public function for(Permission $permission): array
    {
        return [
            'permission' => $permission->name,
            'versions' => $permission->versions()
                ->orderBy('version')
                ->get()
                ->map(fn (PermissionVersion $version) => [
                    'version' => $version->version,
                    'definition' => $version->definition,
                    'changed_by' => $version->changed_by,
                    'change_reason' => $version->change_reason,
                    'created_at' => optional($version->created_at)?->toIso8601String(),
                ])
                ->all(),
            'audits' => AuthorizationAudit::query()
                ->where('permission_id', $permission->getKey())
                ->orderByDesc('created_at')
                ->limit(100)
                ->get()
                ->map(fn (AuthorizationAudit $audit) => $audit->toArray())
                ->all(),
        ];
    }

    /**
     * @return Collection<int, PermissionVersion>
     */
    public function versions(Permission $permission): Collection
    {
        return $permission->versions()->orderBy('version')->get();
    }
}
