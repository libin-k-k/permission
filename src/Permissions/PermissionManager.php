<?php

namespace Libinkk\Permission\Permissions;

class PermissionManager
{
    public function findOrCreate(string $name, ?string $guard = null): Permission
    {
        return Permission::findOrCreate($name, $guard);
    }
}
