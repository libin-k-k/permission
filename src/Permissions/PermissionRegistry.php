<?php

namespace Libinkk\Permission\Permissions;

use Libinkk\Permission\Contracts\PermissionRepository;

class PermissionRegistry
{
    public function __construct(
        protected PermissionRepository $permissions,
        protected PermissionResolver $resolver,
    ) {
    }

    public function has(string $name, ?string $guard = null): bool
    {
        $guard ??= config('permission.default_guard', 'web');

        return $this->resolver->isRegistered($name, $guard);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(?string $guard = null): array
    {
        $guard ??= config('permission.default_guard', 'web');

        return $this->permissions->allActive($guard);
    }
}
