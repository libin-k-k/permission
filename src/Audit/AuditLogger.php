<?php

namespace Libinkk\Permission\Audit;

use Illuminate\Http\Request;
use Libinkk\Permission\Authorization\Decision;
use Libinkk\Permission\Contracts\AuditLogger as AuditLoggerContract;
use Libinkk\Permission\Delegation\Delegation;
use Libinkk\Permission\Events\AuthorizationAllowed;
use Libinkk\Permission\Events\AuthorizationDenied;
use Libinkk\Permission\Events\DelegationCreated;
use Libinkk\Permission\Events\DelegationRevoked;
use Libinkk\Permission\Events\PermissionGranted;
use Libinkk\Permission\Events\PermissionRevoked;
use Libinkk\Permission\Events\PolicyChanged;
use Libinkk\Permission\Events\RoleAssigned;
use Libinkk\Permission\Events\RoleRemoved;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Throwable;

class AuditLogger implements AuditLoggerContract
{
    public function enabled(): bool
    {
        return (bool) config('permission.audit.enabled', false);
    }

    public function decisionsEnabled(): bool
    {
        return $this->enabled() && (bool) config('permission.audit.decisions', false);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(string $event, array $payload = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            AuthorizationAudit::query()->create(array_merge([
                'reason' => $event,
                'created_at' => now(),
            ], $this->requestContext(), $payload));
        } catch (Throwable) {
            // Audit write failures must never change authorization.
        }
    }

    public function permissionGranted(object $user, Permission $permission): void
    {
        $this->record('permission.granted', [
            ...$this->userColumns($user),
            'permission_id' => $permission->getKey(),
            'result' => 'allowed',
            'reason_code' => 'PERMISSION_GRANTED',
            'decision_source' => 'assignment',
            'metadata' => ['permission' => $permission->name],
        ]);
    }

    public function permissionRevoked(object $user, Permission $permission): void
    {
        $this->record('permission.revoked', [
            ...$this->userColumns($user),
            'permission_id' => $permission->getKey(),
            'result' => 'denied',
            'reason_code' => 'PERMISSION_REVOKED',
            'decision_source' => 'assignment',
            'metadata' => ['permission' => $permission->name],
        ]);
    }

    public function roleAssigned(object $user, Role $role): void
    {
        $this->record('role.assigned', [
            ...$this->userColumns($user),
            'role_id' => $role->getKey(),
            'result' => 'allowed',
            'reason_code' => 'ROLE_ASSIGNED',
            'decision_source' => 'assignment',
            'metadata' => ['role' => $role->slug],
        ]);
    }

    public function roleRemoved(object $user, Role $role): void
    {
        $this->record('role.removed', [
            ...$this->userColumns($user),
            'role_id' => $role->getKey(),
            'result' => 'denied',
            'reason_code' => 'ROLE_REMOVED',
            'decision_source' => 'assignment',
            'metadata' => ['role' => $role->slug],
        ]);
    }

    public function delegationCreated(Delegation $delegation): void
    {
        $this->record('delegation.created', [
            'user_type' => $delegation->to_user_type,
            'user_id' => $delegation->to_user_id,
            'permission_id' => $delegation->permission_id,
            'scope_id' => $delegation->scope_id,
            'resource_type' => $delegation->resource_type,
            'resource_id' => $delegation->resource_id,
            'result' => 'allowed',
            'reason_code' => 'DELEGATION_CREATED',
            'decision_source' => 'delegation',
            'metadata' => [
                'from_user_type' => $delegation->from_user_type,
                'from_user_id' => $delegation->from_user_id,
                'delegation_id' => $delegation->getKey(),
                'expires_at' => optional($delegation->expires_at)?->toIso8601String(),
            ],
        ]);
    }

    public function delegationRevoked(Delegation $delegation): void
    {
        $this->record('delegation.revoked', [
            'user_type' => $delegation->to_user_type,
            'user_id' => $delegation->to_user_id,
            'permission_id' => $delegation->permission_id,
            'scope_id' => $delegation->scope_id,
            'result' => 'denied',
            'reason_code' => 'DELEGATION_REVOKED',
            'decision_source' => 'delegation',
            'metadata' => [
                'delegation_id' => $delegation->getKey(),
                'from_user_type' => $delegation->from_user_type,
                'from_user_id' => $delegation->from_user_id,
            ],
        ]);
    }

    public function policyChanged(Permission $permission, int $version): void
    {
        $this->record('policy.changed', [
            'permission_id' => $permission->getKey(),
            'reason_code' => 'POLICY_CHANGED',
            'decision_source' => 'versioning',
            'metadata' => [
                'permission' => $permission->name,
                'version' => $version,
            ],
        ]);
    }

    public function decision(Decision $decision, bool $allowed): void
    {
        if (! $this->shouldLogDecision($decision)) {
            return;
        }

        $event = $allowed ? 'authorization.allowed' : 'authorization.denied';

        if (! $allowed && $decision->reason === \Libinkk\Permission\Authorization\DecisionReason::EXPIRED_PERMISSION) {
            $event = 'permission.expired';
        }

        $this->record($event, [
            ...$this->userColumns(is_object($decision->user) ? $decision->user : null),
            ...$this->resourceColumns($decision->resource),
            'permission_id' => $this->permissionId($decision->permission),
            'result' => $allowed ? 'allowed' : 'denied',
            'reason_code' => $decision->reason,
            'decision_source' => $decision->source,
            'metadata' => [
                'permission' => $decision->permission,
                'role' => $decision->role,
                'scope' => $decision->scope,
                'source' => $decision->source,
            ],
        ]);
    }

    public function handlePermissionGranted(PermissionGranted $event): void
    {
        $this->permissionGranted($event->user, $event->permission);
    }

    public function handlePermissionRevoked(PermissionRevoked $event): void
    {
        $this->permissionRevoked($event->user, $event->permission);
    }

    public function handleRoleAssigned(RoleAssigned $event): void
    {
        $this->roleAssigned($event->user, $event->role);
    }

    public function handleRoleRemoved(RoleRemoved $event): void
    {
        $this->roleRemoved($event->user, $event->role);
    }

    public function handleDelegationCreated(DelegationCreated $event): void
    {
        $this->delegationCreated($event->delegation);
    }

    public function handleDelegationRevoked(DelegationRevoked $event): void
    {
        $this->delegationRevoked($event->delegation);
    }

    public function handlePolicyChanged(PolicyChanged $event): void
    {
        $this->policyChanged($event->permission, (int) $event->version->version);
    }

    public function handleAuthorizationAllowed(AuthorizationAllowed $event): void
    {
        $this->decision($event->decision, true);
    }

    public function handleAuthorizationDenied(AuthorizationDenied $event): void
    {
        $this->decision($event->decision, false);
    }

    protected function shouldLogDecision(Decision $decision): bool
    {
        if ($this->decisionsEnabled()) {
            return true;
        }

        if (! $this->enabled()) {
            return false;
        }

        $permission = Permission::query()->where('name', $decision->permission)->first();

        return $permission?->requires_audit === true;
    }

    /**
     * @return array{user_type: ?string, user_id: mixed}
     */
    protected function userColumns(?object $user): array
    {
        if ($user === null) {
            return ['user_type' => null, 'user_id' => null];
        }

        return [
            'user_type' => method_exists($user, 'getMorphClass') ? $user->getMorphClass() : $user::class,
            'user_id' => method_exists($user, 'getKey') ? $user->getKey() : null,
        ];
    }

    /**
     * @return array{resource_type: ?string, resource_id: ?string}
     */
    protected function resourceColumns(mixed $resource): array
    {
        if (! is_object($resource)) {
            return ['resource_type' => null, 'resource_id' => null];
        }

        return [
            'resource_type' => method_exists($resource, 'getMorphClass') ? $resource->getMorphClass() : $resource::class,
            'resource_id' => method_exists($resource, 'getKey') ? (string) $resource->getKey() : null,
        ];
    }

    /**
     * @return array{request_id: ?string, ip_address: ?string, user_agent: ?string}
     */
    protected function requestContext(): array
    {
        try {
            $request = app()->bound('request') ? app('request') : null;
        } catch (Throwable) {
            $request = null;
        }

        if (! $request instanceof Request) {
            return [
                'request_id' => null,
                'ip_address' => null,
                'user_agent' => null,
            ];
        }

        return [
            'request_id' => $request->headers->get('X-Request-Id')
                ?: $request->headers->get('X-Request-ID')
                ?: $request->attributes->get('request_id'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }

    protected function permissionId(string $name): mixed
    {
        return Permission::query()->where('name', $name)->value('id');
    }
}
