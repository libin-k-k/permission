<?php

namespace Libinkk\Permission\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\TestCase;

class GateIntegrationTest extends TestCase
{
    public function test_gate_allows_assigned_permission(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.create');

        $this->assertTrue(Gate::forUser($user)->allows('posts.create'));
        $this->assertFalse(Gate::forUser($user)->denies('posts.create'));
    }

    public function test_gate_denies_unassigned_permission(): void
    {
        $user = $this->user();
        Permission::findOrCreate('posts.delete');

        $this->assertTrue(Gate::forUser($user)->denies('posts.delete'));
    }

    public function test_policy_abilities_are_not_intercepted(): void
    {
        $user = $this->user();

        Gate::define('update-profile', fn ($authenticated) => $authenticated->is($user));

        $this->assertTrue(Gate::forUser($user)->allows('update-profile'));
    }

    public function test_role_permissions_work_through_gate(): void
    {
        $user = $this->user();
        Role::findOrCreate('editor')->givePermissionTo('posts.publish');
        $user->assignRole('editor');

        $this->assertTrue($user->can('posts.publish'));
        $this->assertTrue(Gate::forUser($user)->check('posts.publish'));
    }
}
