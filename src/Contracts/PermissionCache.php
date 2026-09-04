<?php

namespace Libinkk\Permission\Contracts;

use Closure;

interface PermissionCache
{
    public function remember(string $key, string $ttlType, Closure $callback): mixed;

    public function get(string $key, bool $persistent = true): mixed;

    public function put(string $key, mixed $value, string $ttlType, bool $persistent = true): void;

    public function forget(string $key): void;

    public function forgetUser(object $user): void;

    public function forgetRole(string $slug): void;

    public function forgetPermission(string $name): void;

    public function forgetRegistry(?string $guard = null): void;

    public function flushRequestCache(): void;

    public function userKey(object $user): string;

    public function generations(object $user): string;

    public function prefix(): string;
}
