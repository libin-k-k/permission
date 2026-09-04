<?php

namespace Libinkk\Permission\Support;

/**
 * Configurable authorization table names.
 */
final class Tables
{
    public static function get(string $name, ?string $default = null): string
    {
        return (string) config("permission.table_names.{$name}", $default ?? $name);
    }

    public static function roles(): string
    {
        return self::get('roles', 'roles');
    }

    public static function permissions(): string
    {
        return self::get('permissions', 'permissions');
    }

    public static function rolePermissions(): string
    {
        return self::get('role_permissions', 'role_permissions');
    }

    public static function userRoles(): string
    {
        return self::get('user_roles', 'user_roles');
    }

    public static function userPermissions(): string
    {
        return self::get('user_permissions', 'user_permissions');
    }

    public static function roleInheritances(): string
    {
        return self::get('role_inheritances', 'role_inheritances');
    }

    public static function permissionConditions(): string
    {
        return self::get('permission_conditions', 'permission_conditions');
    }

    public static function permissionConditionValues(): string
    {
        return self::get('permission_condition_values', 'permission_condition_values');
    }
}
