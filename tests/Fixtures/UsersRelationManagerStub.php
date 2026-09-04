<?php

namespace Libinkk\Permission\Tests\Fixtures;

use Libinkk\Permission\Filament\AuthorizesFilamentRelationManager;

class UsersRelationManagerStub
{
    use AuthorizesFilamentRelationManager;

    public static ?string $permissionResource = 'users';
}
