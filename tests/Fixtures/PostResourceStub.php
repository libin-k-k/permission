<?php

namespace Libinkk\Permission\Tests\Fixtures;

use Libinkk\Permission\Filament\AuthorizesFilamentResource;

class PostResourceStub
{
    use AuthorizesFilamentResource;

    public static ?string $permissionResource = 'posts';
}
