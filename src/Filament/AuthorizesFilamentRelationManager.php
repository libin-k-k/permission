<?php

namespace Libinkk\Permission\Filament;

/**
 * Drop onto a Filament RelationManager. Maps view/create/edit/delete/attach/detach/…
 *
 * @property string|null $permissionResource
 */
trait AuthorizesFilamentRelationManager
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

    public static function canViewForRecord(mixed $ownerRecord, string $pageClass): bool
    {
        return static::authorizePermission('view', $ownerRecord);
    }

    public function canCreate(): bool
    {
        return static::authorizePermission('create');
    }

    public function canEdit(mixed $record): bool
    {
        return static::authorizePermission('update', $record);
    }

    public function canDelete(mixed $record): bool
    {
        return static::authorizePermission('delete', $record);
    }

    public function canDeleteAny(): bool
    {
        return static::authorizePermission('deleteAny');
    }

    public function canAttach(): bool
    {
        return static::authorizePermission('attach');
    }

    public function canDetach(): bool
    {
        return static::authorizePermission('detach');
    }

    public function canDetachAny(): bool
    {
        return static::authorizePermission('detach');
    }

    public function canAssociate(): bool
    {
        return static::authorizePermission('associate');
    }

    public function canDissociate(): bool
    {
        return static::authorizePermission('dissociate');
    }

    public function canDissociateAny(): bool
    {
        return static::authorizePermission('dissociate');
    }
}
