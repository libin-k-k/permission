<?php

namespace Libinkk\Permission\Authorization;

use BadMethodCallException;
use Libinkk\Permission\Contracts\PermissionCache;

/**
 * Request-scoped tenant/scope for authorization checks.
 *
 * @method static void tenant(mixed $tenant)
 * @method static void scope(mixed $scope)
 */
final class AuthorizationContext
{
    protected static mixed $activeTenant = null;

    protected static mixed $activeScope = null;

    public function __construct(
        public object $user,
        public string $permission,
        public string $guard,
        public mixed $resource = null,
        public array $arguments = [],
    ) {
    }

    public static function __callStatic(string $name, array $arguments): void
    {
        match ($name) {
            'tenant' => self::setTenant($arguments[0] ?? null),
            'scope' => self::setScope($arguments[0] ?? null),
            default => throw new BadMethodCallException("AuthorizationContext::{$name}() is not defined."),
        };
    }

    /**
     * Set the current tenant for this request. Subsequent $user->can() checks resolve against it.
     */
    public static function setTenant(mixed $value): void
    {
        self::$activeTenant = $value;
        self::$activeScope = $value;
        self::invalidateRequestCache();
    }

    /**
     * Switch tenant and drop request-level authorization cache.
     */
    public static function switch(mixed $value): void
    {
        self::setTenant($value);
    }

    public static function setScope(mixed $value): void
    {
        self::$activeScope = $value;
        if (self::$activeTenant === null) {
            self::$activeTenant = $value;
        }
        self::invalidateRequestCache();
    }

    public static function currentTenant(): mixed
    {
        return self::$activeTenant;
    }

    public static function currentScope(): mixed
    {
        return self::$activeScope;
    }

    /**
     * Most specific current boundary: nested scope, else tenant.
     */
    public static function currentTarget(): mixed
    {
        return self::$activeScope ?? self::$activeTenant;
    }

    public static function flush(): void
    {
        self::$activeTenant = null;
        self::$activeScope = null;
    }

    public function hasResource(): bool
    {
        return $this->resource !== null;
    }

    public function userKey(): string
    {
        $type = method_exists($this->user, 'getMorphClass')
            ? $this->user->getMorphClass()
            : $this->user::class;

        $id = method_exists($this->user, 'getKey')
            ? $this->user->getKey()
            : spl_object_id($this->user);

        return $type.':'.$id;
    }

    public function resourceKey(): ?string
    {
        if (! is_object($this->resource)) {
            return $this->resource === null ? null : (string) $this->resource;
        }

        $type = $this->resource::class;
        $id = method_exists($this->resource, 'getKey') ? $this->resource->getKey() : spl_object_id($this->resource);

        return $type.':'.$id;
    }

    protected static function invalidateRequestCache(): void
    {
        if (function_exists('app') && app()->bound(PermissionCache::class)) {
            app(PermissionCache::class)->flushRequestCache();
        }
    }
}
