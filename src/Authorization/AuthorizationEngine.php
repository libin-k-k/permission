<?php

namespace Libinkk\Permission\Authorization;

use Libinkk\Permission\Cache\DecisionCache;
use Libinkk\Permission\Cache\PermissionFake;
use Libinkk\Permission\Conditions\ConditionResolver;
use Libinkk\Permission\Contracts\AuthorizationEngine as AuthorizationEngineContract;
use Libinkk\Permission\Delegation\DelegationManager;
use Libinkk\Permission\Permissions\PermissionResolver;
use Libinkk\Permission\Scopes\ScopeResolver;
use Throwable;

class AuthorizationEngine implements AuthorizationEngineContract
{
    private static int $resolving = 0;

    public function __construct(
        protected PermissionResolver $resolver,
        protected DecisionCache $decisions,
        protected ConditionResolver $conditions,
        protected ScopeResolver $scopes,
        protected DelegationManager $delegations,
        protected ExpirationChecker $expirations,
    ) {
    }

    public static function isResolving(): bool
    {
        return self::$resolving > 0;
    }

    public function manages(string $ability): bool
    {
        if (PermissionFake::isActive() && PermissionFake::instance()->has($ability)) {
            return true;
        }

        if (str_contains($ability, '.')) {
            return true;
        }

        $guard = config('permission.default_guard', 'web');

        return $this->resolver->isRegistered($ability, $guard);
    }

    public function allows(object $user, string $permission, array $arguments = []): bool
    {
        return $this->decide($user, $permission, $arguments)->allowed();
    }

    public function decide(object $user, string $permission, array $arguments = []): Decision
    {
        if (self::$resolving > 0) {
            return $this->resolveDecision($user, $permission, $arguments);
        }

        self::$resolving++;

        try {
            return $this->resolveDecision($user, $permission, $arguments);
        } finally {
            self::$resolving--;
        }
    }

    protected function resolveDecision(object $user, string $permission, array $arguments = []): Decision
    {
        try {
            return $this->decideOrFail($user, $permission, $arguments);
        } catch (Throwable) {
            return Decision::deny(
                permission: $permission,
                user: $user,
                resource: $arguments[0] ?? null,
                reason: DecisionReason::CONTEXT_MISSING,
                source: 'engine',
            );
        }
    }

    protected function decideOrFail(object $user, string $permission, array $arguments = []): Decision
    {
        if (! is_string($permission) || trim($permission) === '' || str_contains($permission, "\0")) {
            return Decision::deny(
                permission: is_string($permission) ? $permission : '',
                user: $user,
                resource: $arguments[0] ?? null,
                reason: DecisionReason::CONTEXT_MISSING,
                source: 'engine',
            );
        }

        $resource = $arguments[0] ?? null;
        $guard = $this->guardFor($user);
        $context = new AuthorizationContext($user, $permission, $guard, $resource, $arguments);
        $scopeLabel = $this->scopes->label();

        if (PermissionFake::isActive() && PermissionFake::instance()->has($permission)) {
            return PermissionFake::instance()->allowed($permission)
                ? Decision::allow($permission, $user, resource: $resource, source: 'fake')
                : Decision::deny($permission, $user, resource: $resource, reason: DecisionReason::EXPLICIT_DENY, source: 'fake');
        }

        if ($this->scopes->teamsEnabled()) {
            $target = AuthorizationContext::currentTarget();

            if (config('permission.teams.require_context') && $target === null) {
                return Decision::deny(
                    permission: $permission,
                    user: $user,
                    resource: $resource,
                    reason: DecisionReason::CONTEXT_MISSING,
                    source: 'engine',
                    checks: ['context' => false],
                );
            }

            if ($target !== null && config('permission.teams.require_membership') && ! $this->scopes->userCanAccess($user, $target)) {
                $reason = $this->scopes->isNestedScope($target)
                    ? DecisionReason::SCOPE_MISMATCH
                    : DecisionReason::TENANT_MISMATCH;

                $decision = Decision::deny(
                    permission: $permission,
                    user: $user,
                    resource: $resource,
                    reason: $reason,
                    source: 'engine',
                    checks: [
                        'tenant' => $reason !== DecisionReason::TENANT_MISMATCH,
                        'scope' => false,
                    ],
                );
                $decision->scope = $scopeLabel;

                return $decision;
            }
        }

        $hasDynamicConditions = $this->conditions->hasConditions($permission) || $context->hasResource();

        $cacheKey = $this->decisions->keyFor(
            $context->userKey().'|'.$this->resolver->permissionMapCacheSalt($user, $guard),
            $permission,
            $guard,
            $context->resourceKey()
        );

        if (! $hasDynamicConditions) {
            $cached = $this->decisions->get($cacheKey, $context->hasResource());

            if ($cached instanceof Decision) {
                return $cached;
            }
        }

        $map = $this->resolver->permissionMapFor($user, $guard);
        $entry = $this->resolver->matchPermission($map, $permission);

        if ($entry === null) {
            $delegation = $this->delegations->activeFor($user, $permission, $resource);

            if ($delegation !== null) {
                $entry = [
                    'effect' => 'allow',
                    'source' => 'delegation',
                    'role' => null,
                    'layer' => Precedence::EXPLICIT_ALLOW,
                    'matched' => $delegation['permission'],
                    'via' => 'delegation',
                    'delegation_id' => $delegation['id'],
                ];
            }
        }

        if ($entry === null) {
            $reason = $this->missingReason($user);

            if ($this->expirations->expiredGrantExists($user, $permission, $guard)) {
                $reason = DecisionReason::EXPIRED_PERMISSION;
            } elseif ($this->delegations->expiredFor($user, $permission, $resource)) {
                $reason = DecisionReason::DELEGATION_EXPIRED;
            }

            $decision = Decision::deny(
                permission: $permission,
                user: $user,
                resource: $resource,
                reason: $reason,
                source: 'engine',
                checks: [
                    'permission' => false,
                    'role' => $this->resolver->rolesFor($user, $guard) !== [],
                    'wildcard' => false,
                    'conditions' => null,
                    'tenant' => AuthorizationContext::currentTarget() !== null,
                    'expired' => $reason === DecisionReason::EXPIRED_PERMISSION,
                    'delegation' => $reason === DecisionReason::DELEGATION_EXPIRED,
                ],
            );
        } elseif (Precedence::isDeny($entry['layer']) || ($entry['effect'] ?? 'allow') === 'deny') {
            $decision = Decision::deny(
                permission: $permission,
                user: $user,
                role: $entry['role'] ?? null,
                resource: $resource,
                reason: DecisionReason::EXPLICIT_DENY,
                source: $entry['source'],
                metadata: [
                    'matched' => $entry['matched'],
                    'via' => $entry['via'],
                    'layer' => $entry['layer'],
                    'effect' => $entry['effect'],
                ],
                checks: [
                    'permission' => true,
                    'denied' => true,
                    'role' => ($entry['role'] ?? null) !== null,
                    'wildcard' => str_starts_with($entry['via'], 'wildcard'),
                    'conditions' => null,
                ],
            );
        } else {
            $conditionResult = $this->conditions->evaluate($user, $permission, $resource, $arguments);

            if (! $conditionResult['passed']) {
                $reason = str_ends_with($permission, '.own') && ($conditionResult['results']['owner'] ?? true) === false
                    ? DecisionReason::RESOURCE_DENIED
                    : DecisionReason::CONDITION_FAILED;

                $decision = Decision::deny(
                    permission: $permission,
                    user: $user,
                    role: $entry['role'] ?? null,
                    resource: $resource,
                    reason: $reason,
                    source: $entry['source'],
                    metadata: [
                        'matched' => $entry['matched'],
                        'via' => $entry['via'],
                        'layer' => $entry['layer'],
                    ],
                    checks: [
                        'permission' => true,
                        'role' => ($entry['role'] ?? null) !== null,
                        'wildcard' => str_starts_with($entry['via'], 'wildcard'),
                        'conditions' => false,
                    ],
                );
                $decision->conditions = $conditionResult['results'];
            } else {
                $decision = Decision::allow(
                    permission: $permission,
                    user: $user,
                    role: $entry['role'] ?? null,
                    resource: $resource,
                    source: $entry['source'],
                    metadata: [
                        'matched' => $entry['matched'],
                        'via' => $entry['via'],
                        'layer' => $entry['layer'],
                        'delegation_id' => $entry['delegation_id'] ?? null,
                    ],
                    checks: [
                        'permission' => true,
                        'role' => ($entry['role'] ?? null) !== null,
                        'wildcard' => str_starts_with($entry['via'], 'wildcard'),
                        'conditions' => $conditionResult['results'] === [] ? null : true,
                        'delegation' => ($entry['source'] ?? null) === 'delegation',
                    ],
                );
                $decision->conditions = $conditionResult['results'];
            }
        }

        $decision->scope = $scopeLabel;

        $ephemeral = ($decision->source === 'delegation')
            || in_array($decision->reason, [
                DecisionReason::EXPIRED_PERMISSION,
                DecisionReason::DELEGATION_EXPIRED,
            ], true);

        if (! $hasDynamicConditions && ! $ephemeral) {
            $this->decisions->put($cacheKey, $decision, $context->hasResource());
        }

        if ($decision->allowed()) {
            event(new \Libinkk\Permission\Events\AuthorizationAllowed($decision));
        } else {
            event(new \Libinkk\Permission\Events\AuthorizationDenied($decision));
        }

        return $decision;
    }

    protected function guardFor(object $user): string
    {
        if (method_exists($user, 'authorizationGuard')) {
            return (string) $user->authorizationGuard();
        }

        if (isset($user->guard_name) && is_string($user->guard_name) && $user->guard_name !== '') {
            return $user->guard_name;
        }

        return (string) config('permission.default_guard', 'web');
    }

    protected function missingReason(object $user): string
    {
        if (! $this->scopes->teamsEnabled()) {
            return DecisionReason::PERMISSION_MISSING;
        }

        $target = AuthorizationContext::currentTarget();

        if ($target === null) {
            return DecisionReason::PERMISSION_MISSING;
        }

        if ($this->scopes->userHasOtherTenantAssignments($user, $target)) {
            return $this->scopes->isNestedScope($target)
                ? DecisionReason::SCOPE_MISMATCH
                : DecisionReason::TENANT_MISMATCH;
        }

        return DecisionReason::PERMISSION_MISSING;
    }
}
