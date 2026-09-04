<?php

namespace Libinkk\Permission\Authorization;

use Libinkk\Permission\Cache\DecisionCache;
use Libinkk\Permission\Cache\PermissionFake;
use Libinkk\Permission\Contracts\AuthorizationEngine as AuthorizationEngineContract;
use Libinkk\Permission\Permissions\PermissionResolver;
use Throwable;

class AuthorizationEngine implements AuthorizationEngineContract
{
    private static int $resolving = 0;

    public function __construct(
        protected PermissionResolver $resolver,
        protected DecisionCache $decisions,
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
        $resource = $arguments[0] ?? null;
        $guard = $this->guardFor($user);
        $context = new AuthorizationContext($user, $permission, $guard, $resource, $arguments);

        if (PermissionFake::isActive() && PermissionFake::instance()->has($permission)) {
            return PermissionFake::instance()->allowed($permission)
                ? Decision::allow($permission, $user, resource: $resource, source: 'fake')
                : Decision::deny($permission, $user, resource: $resource, reason: DecisionReason::EXPLICIT_DENY, source: 'fake');
        }

        $cacheKey = $this->decisions->keyFor(
            $context->userKey().'|'.$this->resolver->permissionMapCacheSalt($user, $guard),
            $permission,
            $guard,
            $context->resourceKey()
        );

        $cached = $this->decisions->get($cacheKey, $context->hasResource());

        if ($cached instanceof Decision) {
            return $cached;
        }

        $map = $this->resolver->permissionMapFor($user, $guard);
        $entry = $this->resolver->matchPermission($map, $permission);

        if ($entry === null) {
            $decision = Decision::deny(
                permission: $permission,
                user: $user,
                resource: $resource,
                reason: DecisionReason::PERMISSION_MISSING,
                source: 'engine',
                checks: [
                    'permission' => false,
                    'role' => $this->resolver->rolesFor($user, $guard) !== [],
                    'wildcard' => false,
                ],
            );
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
                ],
                checks: [
                    'permission' => true,
                    'role' => ($entry['role'] ?? null) !== null,
                    'wildcard' => str_starts_with($entry['via'], 'wildcard'),
                ],
            );
        }

        $this->decisions->put($cacheKey, $decision, $context->hasResource());

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
}
