<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Authorization\DecisionReason;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\TestCase;

class ExplicitDenyTest extends TestCase
{
    public function test_user_deny_overrides_role_allow(): void
    {
        $role = Role::findOrCreate('admin');
        $role->givePermissionTo('posts.delete');

        $user = $this->user();
        $user->assignRole($role);
        $user->denyPermissionTo('posts.delete');

        $decision = $this->assertCannot($user, 'posts.delete');
        $this->assertSame(DecisionReason::EXPLICIT_DENY, $decision->reason);
        $this->assertSame('direct', $decision->source);
    }

    public function test_role_deny_overrides_inherited_allow(): void
    {
        $viewer = Role::findOrCreate('viewer');
        $editor = Role::findOrCreate('editor');
        $viewer->givePermissionTo('posts.delete');
        Role::inherit('editor', 'viewer');
        $editor->denyPermissionTo('posts.delete');

        $user = $this->user();
        $user->assignRole('editor');

        $decision = $this->assertCannot($user, 'posts.delete');
        $this->assertSame(DecisionReason::EXPLICIT_DENY, $decision->reason);
    }

    public function test_wildcard_allow_with_exact_deny(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.*');
        Permission::findOrCreate('posts.view');
        Permission::findOrCreate('posts.delete');
        $user->denyPermissionTo('posts.delete');

        $this->assertCan($user, 'posts.view');
        $this->assertCannot($user, 'posts.delete');
    }
}
