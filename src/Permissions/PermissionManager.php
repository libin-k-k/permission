<?php

namespace Libinkk\Permission\Permissions;

class PermissionManager
{
    public function findOrCreate(string $name, ?string $guard = null, array $attributes = []): Permission
    {
        return Permission::findOrCreate($name, $guard, $attributes);
    }

    /**
     * @param  list<string>  $actions
     * @param  array<string, mixed>  $attributes
     * @return \Illuminate\Support\Collection<int, Permission>
     */
    public function defineResource(string $resource, array $actions, array $attributes = [])
    {
        return Permission::defineResource($resource, $actions, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return \Illuminate\Support\Collection<int, Permission>
     */
    public function crud(string $resource, array $attributes = [])
    {
        return Permission::crud($resource, $attributes);
    }
}
