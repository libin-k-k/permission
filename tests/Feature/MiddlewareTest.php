<?php

namespace Libinkk\Permission\Tests\Feature;

use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\TestCase;

class MiddlewareTest extends TestCase
{
    public function test_permission_middleware_allows_authorized_user(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.create');

        $this->actingAs($user)
            ->get('/permission-check')
            ->assertOk();
    }

    public function test_permission_middleware_denies_unauthorized_user(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->get('/permission-check')
            ->assertForbidden();
    }

    public function test_permission_middleware_denies_guest(): void
    {
        $this->get('/permission-check')->assertForbidden();
    }

    public function test_permission_middleware_or_logic(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.update');

        $this->actingAs($user)
            ->get('/permission-any')
            ->assertOk();
    }

    public function test_role_middleware_allows_matching_role(): void
    {
        $user = $this->user();
        Role::findOrCreate('admin');
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/role-check')
            ->assertOk();
    }

    public function test_role_middleware_denies_missing_role(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->get('/role-check')
            ->assertForbidden();
    }

    public function test_role_middleware_or_logic(): void
    {
        $user = $this->user();
        Role::findOrCreate('editor');
        $user->assignRole('editor');

        $this->actingAs($user)
            ->get('/role-any')
            ->assertOk();
    }
}
