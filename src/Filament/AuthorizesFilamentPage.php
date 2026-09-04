<?php

namespace Libinkk\Permission\Filament;

/**
 * Drop onto a Filament Page. Set `$permission` (e.g. reports.view) or `$permissionResource`.
 *
 * @property string|null $permission
 * @property string|null $permissionResource
 */
trait AuthorizesFilamentPage
{
    public static function getPagePermission(): string
    {
        if (isset(static::$permission) && is_string(static::$permission) && static::$permission !== '') {
            return static::$permission;
        }

        $resource = isset(static::$permissionResource) && is_string(static::$permissionResource) && static::$permissionResource !== ''
            ? static::$permissionResource
            : FilamentAuthorization::guessResource(static::class);

        return FilamentAuthorization::permission($resource, 'view');
    }

    public static function canAccess(array $parameters = []): bool
    {
        return FilamentAuthorization::allows(static::getPagePermission());
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        if (! config('permission.filament.navigation', true)) {
            return true;
        }

        return static::canAccess($parameters);
    }
}
