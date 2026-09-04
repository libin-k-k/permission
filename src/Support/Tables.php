<?php

namespace Libinkk\Permission\Support;

final class Tables
{
    public static function roles(): string
    {
        return config('permission.table_names.roles', 'roles');
    }

    public static function permissions(): string
    {
        return config('permission.table_names.permissions', 'permissions');
    }

    public static function rolePermissions(): string
    {
        return config('permission.table_names.role_permissions', 'role_permissions');
    }

    public static function userRoles(): string
    {
        return config('permission.table_names.user_roles', 'user_roles');
    }

    public static function userPermissions(): string
    {
        return config('permission.table_names.user_permissions', 'user_permissions');
    }
}
