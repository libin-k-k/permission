<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Tests\TestCase;

class PermissionFakeTest extends TestCase
{
    public function test_fake_allow_and_deny(): void
    {
        $user = $this->user();

        Permission::fake();
        Permission::allow('posts.create');
        Permission::deny('posts.delete');

        $this->assertCan($user, 'posts.create');
        $this->assertCannot($user, 'posts.delete');
    }
}
