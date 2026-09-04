<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\TestCase;

class UuidPrimaryKeyTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('permission.database.primary_key', 'uuid');
    }

    public function test_roles_and_permissions_use_uuid_keys(): void
    {
        $role = Role::findOrCreate('editor');
        $permission = Permission::findOrCreate('posts.view');

        $this->assertIsString($role->getKey());
        $this->assertIsString($permission->getKey());
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            (string) $role->getKey()
        );

        $user = $this->user();
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->assertTrue($user->can('posts.view'));
        $this->assertFalse($user->can('posts.delete'));
    }
}
