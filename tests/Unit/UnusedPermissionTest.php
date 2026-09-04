<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Debug\UnusedPermissionFinder;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\TestCase;

class UnusedPermissionTest extends TestCase
{
    public function test_finds_unassigned_and_inactive_permissions(): void
    {
        Permission::findOrCreate('posts.view');
        Permission::findOrCreate('posts.legacy');
        $inactive = Permission::findOrCreate('posts.archived');
        $inactive->forceFill(['is_active' => false])->save();

        Role::findOrCreate('editor')->givePermissionTo('posts.view');
        $this->user()->assignRole('editor');

        $result = app(UnusedPermissionFinder::class)->find();

        $this->assertContains('posts.legacy', $result['unassigned']);
        $this->assertContains('posts.archived', $result['inactive']);
        $this->assertNotContains('posts.view', $result['unassigned']);
        $this->assertGreaterThanOrEqual(2, $result['total']);
    }

    public function test_assigned_without_users_is_detected(): void
    {
        Permission::findOrCreate('reports.export');
        Role::findOrCreate('auditor')->givePermissionTo('reports.export');

        $result = app(UnusedPermissionFinder::class)->find();

        $this->assertContains('reports.export', $result['assigned_without_users']);
    }
}
