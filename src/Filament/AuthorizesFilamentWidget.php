<?php

namespace Libinkk\Permission\Filament;

/**
 * Drop onto a Filament Widget. Set `$permission` or `$permissionResource`.
 *
 * @property string|null $permission
 * @property string|null $permissionResource
 */
trait AuthorizesFilamentWidget
{
    public static function getWidgetPermission(): string
    {
        if (isset(static::$permission) && is_string(static::$permission) && static::$permission !== '') {
            return static::$permission;
        }

        $resource = isset(static::$permissionResource) && is_string(static::$permissionResource) && static::$permissionResource !== ''
            ? static::$permissionResource
            : FilamentAuthorization::guessResource(static::class);

        return FilamentAuthorization::permission($resource, 'view');
    }

    public static function canView(): bool
    {
        return FilamentAuthorization::allows(static::getWidgetPermission());
    }
}
