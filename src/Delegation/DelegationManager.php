<?php

namespace Libinkk\Permission\Delegation;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Libinkk\Permission\Authorization\AuthorizationContext;
use Libinkk\Permission\Authorization\Precedence;
use Libinkk\Permission\Cache\PermissionCache;
use Libinkk\Permission\Events\DelegationCreated;
use Libinkk\Permission\Events\DelegationRevoked;
use Libinkk\Permission\Exceptions\CannotDelegatePermissionException;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Permissions\PermissionResolver;
use Libinkk\Permission\Scopes\Scope;
use Libinkk\Permission\Scopes\ScopeResolver;
use Libinkk\Permission\Support\WildcardMatcher;

class DelegationManager
{
    public function __construct(
        protected PermissionResolver $resolver,
        protected PermissionCache $cache,
        protected ScopeResolver $scopes,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config('permission.delegation.enabled', true);
    }

    public function create(
        object $from,
        object $to,
        string $permission,
        DateTimeInterface|string|null $until = null,
        DateTimeInterface|string|null $startsAt = null,
        mixed $scope = null,
        mixed $resource = null,
        ?string $reason = null,
    ): Delegation {
        if (! $this->enabled()) {
            throw CannotDelegatePermissionException::disabled();
        }

        $guard = $this->guardFor($from);
        $model = Permission::findOrCreate($permission, $guard);

        if ($this->morphType($from) === $this->morphType($to)
            && (string) $from->getKey() === (string) $to->getKey()) {
            throw CannotDelegatePermissionException::selfDelegation();
        }

        if (! $this->userCurrentlyHolds($from, $model->name, $guard)) {
            throw CannotDelegatePermissionException::missing($model->name);
        }

        $starts = $startsAt ? Carbon::parse($startsAt) : now();
        $expires = $until ? Carbon::parse($until) : null;
        $now = now();

        $status = Delegation::STATUS_ACTIVE;
        if ($starts->gt($now)) {
            $status = Delegation::STATUS_PENDING;
        } elseif ($expires && $expires->lte($now)) {
            $status = Delegation::STATUS_EXPIRED;
        }

        $scopeId = $this->resolveScopeId($scope ?? AuthorizationContext::currentTarget());

        $delegation = Delegation::query()->create([
            'from_user_type' => $this->morphType($from),
            'from_user_id' => $from->getKey(),
            'to_user_type' => $this->morphType($to),
            'to_user_id' => $to->getKey(),
            'permission_id' => $model->getKey(),
            'scope_id' => $scopeId,
            'resource_type' => $this->resourceType($resource),
            'resource_id' => $this->resourceId($resource),
            'starts_at' => $starts,
            'expires_at' => $expires,
            'reason' => $reason,
            'status' => $status,
        ]);

        $this->cache->forgetUser($from);
        $this->cache->forgetUser($to);

        event(new DelegationCreated($delegation, $from, $to, $model));

        return $delegation;
    }

    public function revoke(Delegation $delegation, ?string $reason = null, ?object $actor = null): Delegation
    {
        if ($actor === null
            || $this->morphType($actor) !== $delegation->from_user_type
            || (string) $actor->getKey() !== (string) $delegation->from_user_id) {
            throw CannotDelegatePermissionException::cannotRevoke();
        }


        $delegation->status = Delegation::STATUS_REVOKED;
        $delegation->revoked_at = now();

        if ($reason) {
            $delegation->reason = trim((string) $delegation->reason) !== ''
                ? $delegation->reason.' | revoked: '.$reason
                : $reason;
        }

        $delegation->save();

        $this->forgetDelegationUsers($delegation);

        event(new DelegationRevoked($delegation));

        return $delegation->fresh() ?? $delegation;
    }

    /**
     * Active delegation that authorizes this user for the ability (never expired/revoked).
     *
     * @return array{id: mixed, permission: string, from_user_type: string, from_user_id: mixed, reason: ?string}|null
     */
    public function activeFor(object $user, string $permission, mixed $resource = null): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        foreach ($this->received($user) as $row) {
            if (! $this->isLive($row)) {
                continue;
            }

            if (! $this->matchesAbility($row['permission'], $permission)) {
                continue;
            }

            if (! $this->matchesResource($row, $resource)) {
                continue;
            }

            if (! $this->matchesScope($row)) {
                continue;
            }

            if (! $this->delegatorStillHolds($row, $permission)) {
                continue;
            }

            return $row;
        }

        return null;
    }

    /**
     * True when the only related grant is an expired (not revoked) delegation.
     */
    public function expiredFor(object $user, string $permission, mixed $resource = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        foreach ($this->received($user) as $row) {
            if (($row['status'] ?? '') === Delegation::STATUS_REVOKED || $row['revoked_at'] !== null) {
                continue;
            }

            if (! $this->matchesAbility($row['permission'], $permission)) {
                continue;
            }

            if (! $this->matchesResource($row, $resource)) {
                continue;
            }

            if (! $this->matchesScope($row)) {
                continue;
            }

            if ($this->isExpiredRow($row)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function received(object $user): array
    {
        $userKey = $this->cache->userKey($user);
        $generation = $this->cache->generations($user);

        return $this->cache->remember(
            "user:{$userKey}:delegations:{$generation}",
            'delegations',
            fn () => Delegation::query()
                ->with('permission:id,name')
                ->where('to_user_type', $this->morphType($user))
                ->where('to_user_id', $user->getKey())
                ->where('status', '!=', Delegation::STATUS_REVOKED)
                ->get()
                ->map(fn (Delegation $delegation) => $this->toArray($delegation))
                ->all()
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function granted(object $user): array
    {
        return Delegation::query()
            ->with('permission:id,name')
            ->where('from_user_type', $this->morphType($user))
            ->where('from_user_id', $user->getKey())
            ->orderByDesc('id')
            ->get()
            ->map(fn (Delegation $delegation) => $this->toArray($delegation))
            ->all();
    }

    public function expireStale(): int
    {
        $count = Delegation::query()
            ->where('status', Delegation::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => Delegation::STATUS_EXPIRED]);

        $activated = Delegation::query()
            ->where('status', Delegation::STATUS_PENDING)
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->update(['status' => Delegation::STATUS_ACTIVE]);

        return $count + $activated;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function isLive(array $row): bool
    {
        if (($row['status'] ?? '') === Delegation::STATUS_REVOKED || $row['revoked_at'] !== null) {
            return false;
        }

        $now = now();

        if ($row['starts_at'] !== null && Carbon::parse($row['starts_at'])->gt($now)) {
            return false;
        }

        if ($this->isExpiredRow($row)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function isExpiredRow(array $row): bool
    {
        return $row['expires_at'] !== null && Carbon::parse($row['expires_at'])->lte(now());
    }

    protected function matchesAbility(string $delegated, string $requested): bool
    {
        return $delegated === $requested || WildcardMatcher::matches($delegated, $requested);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function matchesResource(array $row, mixed $resource): bool
    {
        if ($row['resource_type'] === null || $row['resource_id'] === null || $row['resource_id'] === '') {
            return true;
        }

        if (! is_object($resource)) {
            return false;
        }

        return $row['resource_type'] === $this->resourceType($resource)
            && (string) $row['resource_id'] === (string) $this->resourceId($resource);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function matchesScope(array $row): bool
    {
        if ($row['scope_id'] === null || $row['scope_id'] === '') {
            return true;
        }

        $current = AuthorizationContext::currentTarget();

        if ($current === null) {
            return false;
        }

        $currentId = $this->resolveScopeId($current);

        if ((string) $currentId === (string) $row['scope_id']) {
            return true;
        }

        if (! config('permission.scopes.inherit', true)) {
            return false;
        }

        $delegated = Scope::query()->find($row['scope_id']);
        $active = $current instanceof Scope ? $current : Scope::for($current);

        if (! $delegated || ! $active) {
            return false;
        }

        return collect(app(\Libinkk\Permission\Scopes\ScopeHierarchy::class)->ancestors($active))
            ->contains(fn (Scope $scope) => (string) $scope->getKey() === (string) $delegated->getKey());
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function delegatorStillHolds(array $row, string $permission): bool
    {
        $from = $this->resolveUser($row['from_user_type'], $row['from_user_id']);

        if ($from === null) {
            return false;
        }

        return $this->userCurrentlyHolds($from, $permission, $this->guardFor($from));
    }

    protected function userCurrentlyHolds(object $user, string $permission, string $guard): bool
    {
        $map = $this->resolver->permissionMapFor($user, $guard);
        $entry = $this->resolver->matchPermission($map, $permission);

        if ($entry === null) {
            return false;
        }

        return ! Precedence::isDeny($entry['layer']) && ($entry['effect'] ?? 'allow') !== 'deny';
    }

    protected function resolveUser(string $type, mixed $id): ?object
    {
        if (! class_exists($type) || ! method_exists($type, 'query')) {
            return null;
        }

        return $type::query()->find($id);
    }

    protected function resolveScopeId(mixed $scope): mixed
    {
        if ($scope === null) {
            return null;
        }

        if ($scope instanceof Scope) {
            return $scope->getKey();
        }

        if (is_object($scope) && method_exists($scope, 'getKey')) {
            return Scope::for($scope)->getKey();
        }

        return null;
    }

    protected function resourceType(mixed $resource): ?string
    {
        if (! is_object($resource)) {
            return null;
        }

        return method_exists($resource, 'getMorphClass') ? $resource->getMorphClass() : $resource::class;
    }

    protected function resourceId(mixed $resource): ?string
    {
        if (! is_object($resource) || ! method_exists($resource, 'getKey')) {
            return null;
        }

        return (string) $resource->getKey();
    }

    protected function morphType(object $user): string
    {
        return method_exists($user, 'getMorphClass') ? $user->getMorphClass() : $user::class;
    }

    protected function guardFor(object $user): string
    {
        if (method_exists($user, 'authorizationGuard')) {
            return (string) $user->authorizationGuard();
        }

        return (string) config('permission.default_guard', 'web');
    }

    protected function forgetDelegationUsers(Delegation $delegation): void
    {
        $from = $this->resolveUser($delegation->from_user_type, $delegation->from_user_id);
        $to = $this->resolveUser($delegation->to_user_type, $delegation->to_user_id);

        if ($from) {
            $this->cache->forgetUser($from);
        }

        if ($to) {
            $this->cache->forgetUser($to);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(Delegation $delegation): array
    {
        return [
            'id' => $delegation->getKey(),
            'permission' => (string) ($delegation->permission?->name ?? ''),
            'permission_id' => $delegation->permission_id,
            'from_user_type' => $delegation->from_user_type,
            'from_user_id' => $delegation->from_user_id,
            'to_user_type' => $delegation->to_user_type,
            'to_user_id' => $delegation->to_user_id,
            'scope_id' => $delegation->scope_id,
            'resource_type' => $delegation->resource_type,
            'resource_id' => $delegation->resource_id,
            'starts_at' => optional($delegation->starts_at)?->toIso8601String(),
            'expires_at' => optional($delegation->expires_at)?->toIso8601String(),
            'reason' => $delegation->reason,
            'status' => $delegation->status,
            'revoked_at' => optional($delegation->revoked_at)?->toIso8601String(),
        ];
    }
}
