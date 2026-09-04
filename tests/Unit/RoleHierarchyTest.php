<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Authorization\DecisionReason;
use Libinkk\Permission\Exceptions\CircularRoleInheritanceException;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\TestCase;

class RoleHierarchyTest extends TestCase
{
    public function test_inherited_permissions_are_granted(): void
    {
        $viewer = Role::findOrCreate('viewer');
        $editor = Role::findOrCreate('editor');
        $admin = Role::findOrCreate('admin');

        $viewer->givePermissionTo('posts.view');
        $editor->givePermissionTo('posts.create');
        Role::inherit('editor', 'viewer');
        Role::inherit('admin', 'editor');

        $user = $this->user();
        $user->assignRole('admin');

        $this->assertCan($user, 'posts.view');
        $this->assertCan($user, 'posts.create');

        $decision = $user->authorizeFor('posts.view');
        $this->assertSame('inherited:viewer', $decision->source);
    }

    public function test_circular_inheritance_is_rejected(): void
    {
        Role::findOrCreate('admin');
        Role::findOrCreate('manager');
        Role::findOrCreate('editor');

        Role::inherit('admin', 'manager');
        Role::inherit('manager', 'editor');

        $this->expectException(CircularRoleInheritanceException::class);
        Role::inherit('editor', 'admin');
    }

    public function test_validate_detects_no_cycle_when_healthy(): void
    {
        Role::inherit('admin', 'editor');
        $this->artisan('permission:validate')->assertSuccessful();
    }
}
