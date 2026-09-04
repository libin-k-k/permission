<?php

namespace Libinkk\Permission\Tests\Feature;

use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\Fixtures\User;
use Libinkk\Permission\Tests\TestCase;

class MultiGuardTest extends TestCase
{
    public function test_permissions_are_isolated_by_guard(): void
    {
        $webUser = $this->user();
        $apiUser = $this->user(['guard_name' => 'api']);

        Permission::findOrCreate('posts.create', 'web');
        Permission::findOrCreate('posts.create', 'api');

        $webUser->givePermissionTo('posts.create');

        $this->assertTrue($webUser->can('posts.create'));
        $this->assertFalse($apiUser->can('posts.create'));

        $apiUser->givePermissionTo(Permission::findOrCreate('posts.create', 'api'));

        $this->assertTrue($apiUser->can('posts.create'));
    }

    public function test_roles_are_isolated_by_guard(): void
    {
        $user = $this->user();
        $apiRole = Role::findOrCreate('editor', 'api');
        $apiRole->givePermissionTo(Permission::findOrCreate('posts.create', 'api'));

        $user->assignRole($apiRole);

        $this->assertFalse($user->can('posts.create'));
        $this->assertFalse($user->hasRole('editor'));
    }

    public function test_polymorphic_user_type_is_stored(): void
    {
        $user = $this->user();
        $user->assignRole(Role::findOrCreate('editor'));

        $this->assertDatabaseHas('user_roles', [
            'user_type' => $user->getMorphClass(),
            'user_id' => $user->getKey(),
        ]);

        $this->assertSame(User::class, $user->getMorphClass());
    }
}
