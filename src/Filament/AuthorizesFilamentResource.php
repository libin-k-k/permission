<?php

namespace Libinkk\Permission\Filament;

/**
 * Drop onto a Filament Resource. Maps canViewAny / canCreate / canEdit / …
 * onto resource.action permissions. Fail closed when no user is present.
 *
 * @property string|null $permissionResource
 */
trait AuthorizesFilamentResource
{
    public static function getPermissionResource(): string
    {
        if (isset(static::$permissionResource) && is_string(static::$permissionResource) && static::$permissionResource !== '') {
            return static::$permissionResource;
        }

        return FilamentAuthorization::guessResource(static::class);
    }

    public static function permissionFor(string $ability): string
    {
        return FilamentAuthorization::permission(static::getPermissionResource(), $ability);
    }

    public static function authorizePermission(string $ability, mixed $record = null): bool
    {
        return FilamentAuthorization::allows(static::permissionFor($ability), $record);
    }

    public static function canViewAny(): bool
    {
        return static::authorizePermission('viewAny');
    }

    public static function canView(mixed $record): bool
    {
        return static::authorizePermission('view', $record);
    }

    public static function canCreate(): bool
    {
        return static::authorizePermission('create');
    }

    public static function canEdit(mixed $record): bool
    {
        return static::authorizePermission('update', $record);
    }

    public static function canDelete(mixed $record): bool
    {
        return static::authorizePermission('delete', $record);
    }

    public static function canDeleteAny(): bool
    {
        return static::authorizePermission('deleteAny');
    }

    public static function canForceDelete(mixed $record): bool
    {
        return static::authorizePermission('forceDelete', $record);
    }

    public static function canForceDeleteAny(): bool
    {
        return static::authorizePermission('forceDeleteAny');
    }

    public static function canRestore(mixed $record): bool
    {
        return static::authorizePermission('restore', $record);
    }

    public static function canRestoreAny(): bool
    {
        return static::authorizePermission('restoreAny');
    }

    public static function canReplicate(mixed $record): bool
    {
        return static::authorizePermission('replicate', $record);
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (! config('permission.filament.navigation', true)) {
            return true;
        }

        return static::canViewAny();
    }
}
