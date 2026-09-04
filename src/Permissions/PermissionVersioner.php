<?php

namespace Libinkk\Permission\Permissions;

use Illuminate\Support\Facades\Auth;
use Libinkk\Permission\Events\PolicyChanged;

class PermissionVersioner
{
    /**
     * @var list<string>
     */
    protected array $snapshotKeys = [
        'name',
        'slug',
        'resource',
        'action',
        'group',
        'description',
        'guard_name',
        'scope_type',
        'is_system',
        'is_active',
        'is_dangerous',
        'risk_level',
        'requires_audit',
        'metadata',
    ];

    public function enabled(): bool
    {
        return (bool) config('permission.versioning.enabled', true);
    }

    public function snapshot(Permission $permission, ?string $reason = null): ?PermissionVersion
    {
        if (! $this->enabled() || ! $permission->getKey()) {
            return null;
        }

        $next = (int) $permission->versions()->max('version') + 1;

        $version = PermissionVersion::query()->create([
            'permission_id' => $permission->getKey(),
            'version' => $next,
            'definition' => $this->definition($permission),
            'changed_by' => $this->changedBy(),
            'change_reason' => $reason ?? ($next === 1 ? 'created' : 'updated'),
            'created_at' => now(),
        ]);

        event(new PolicyChanged($permission, $version));

        return $version;
    }

    public function rollback(Permission $permission, int $version, ?string $reason = null): Permission
    {
        $row = $permission->versions()->where('version', $version)->firstOrFail();
        $definition = is_array($row->definition) ? $row->definition : [];

        foreach ($this->snapshotKeys as $key) {
            if (! array_key_exists($key, $definition)) {
                continue;
            }

            $permission->{$key} = $this->normalizeAttribute($key, $definition[$key]);
        }

        $permission->versionReason = $reason ?? 'rollback to v'.$version;
        $permission->save();

        return $permission->fresh() ?? $permission;
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(Permission $permission): array
    {
        $payload = [];

        foreach ($this->snapshotKeys as $key) {
            $payload[$key] = $this->normalizeAttribute($key, $permission->getAttribute($key));
        }

        return $payload;
    }

    protected function normalizeAttribute(string $key, mixed $value): mixed
    {
        return match ($key) {
            'is_system', 'is_dangerous', 'requires_audit' => (bool) $value,
            'is_active' => $value === null ? true : (bool) $value,
            default => $value,
        };
    }

    protected function changedBy(): ?string
    {
        $user = Auth::user();

        if (! is_object($user)) {
            return null;
        }

        $type = method_exists($user, 'getMorphClass') ? $user->getMorphClass() : $user::class;
        $id = method_exists($user, 'getKey') ? $user->getKey() : null;

        return $id === null ? $type : $type.':'.$id;
    }
}
