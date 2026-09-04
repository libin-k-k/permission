<?php

namespace Libinkk\Permission\Roles;

class RoleManager
{
    public function findOrCreate(string $name, ?string $guard = null): Role
    {
        return Role::findOrCreate($name, $guard);
    }
}
