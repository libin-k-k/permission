<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Support\WildcardMatcher;
use Libinkk\Permission\Tests\TestCase;

class WildcardPermissionTest extends TestCase
{
    public function test_wildcard_matcher(): void
    {
        $this->assertTrue(WildcardMatcher::matches('posts.*', 'posts.view'));
        $this->assertTrue(WildcardMatcher::matches('posts.*', 'posts.view.own'));
        $this->assertFalse(WildcardMatcher::matches('posts.*', 'invoices.view'));
        $this->assertTrue(WildcardMatcher::matches('posts.view.*', 'posts.view.own'));
        $this->assertFalse(WildcardMatcher::matches('posts.view.*', 'posts.view'));
    }

    public function test_resource_wildcard_allows_actions(): void
    {
        $user = $this->user();
        $role = Role::findOrCreate('editor');
        Permission::findOrCreate('posts.*');
        Permission::findOrCreate('posts.view');
        Permission::findOrCreate('posts.create');
        $role->givePermissionTo('posts.*');
        $user->assignRole($role);

        $this->assertCan($user, 'posts.view');
        $this->assertCan($user, 'posts.create');
        $this->assertCannot($user, 'invoices.view');

        $decision = $user->authorizeFor('posts.view');
        $this->assertTrue($decision->checks['wildcard'] ?? false);
        $this->assertSame('wildcard:posts.*', $decision->metadata['via'] ?? null);
    }

    public function test_exact_permission_preferred_over_wildcard(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.*');
        $user->givePermissionTo('posts.view');

        $decision = $user->authorizeFor('posts.view');

        $this->assertTrue($decision->allowed());
        $this->assertSame('exact', $decision->metadata['via'] ?? null);
        $this->assertSame('direct', $decision->source);
    }
}
