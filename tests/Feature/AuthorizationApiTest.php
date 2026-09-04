<?php

namespace Libinkk\Permission\Tests\Feature;

use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\TestCase;

class AuthorizationApiTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('permission.frontend.enabled', true);
    }

    public function test_authorization_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/authorization')->assertUnauthorized();
    }

    public function test_authorization_endpoint_returns_current_user_payload(): void
    {
        Permission::crud('posts');
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('posts.view', 'posts.create');

        $user = $this->user();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->getJson('/api/authorization')
            ->assertOk()
            ->assertJsonPath('user.id', $user->getKey())
            ->assertJsonPath('roles.0', 'editor')
            ->assertJsonFragment(['posts.view'])
            ->assertJsonPath('resources.posts.view', true);
    }

    public function test_user_access_is_self_only_by_default(): void
    {
        $self = $this->user();
        $other = $this->user();
        $self->givePermissionTo('reports.export');
        $this->actingAs($self);

        $this->getJson('/api/users/'.$self->getKey().'/access')
            ->assertOk()
            ->assertJsonFragment(['reports.export']);

        $this->getJson('/api/users/'.$other->getKey().'/access')
            ->assertForbidden();
    }

    public function test_user_access_allows_privileged_viewer(): void
    {
        config()->set('permission.frontend.access_user_permission', 'users.access');

        $admin = $this->user();
        $other = $this->user();
        $admin->givePermissionTo('users.access');
        $other->givePermissionTo('reports.export');
        $this->actingAs($admin);

        $this->getJson('/api/users/'.$other->getKey().'/access')
            ->assertOk()
            ->assertJsonFragment(['reports.export']);
    }

    public function test_permission_matrix_endpoint(): void
    {
        Permission::crud('posts');
        Role::findOrCreate('editor')->givePermissionTo('posts.view');

        $this->actingAs($this->user());

        $this->getJson('/api/permissions/matrix')
            ->assertOk()
            ->assertJsonPath('roles.editor.posts.view', true)
            ->assertJsonPath('roles.editor.posts.delete', false);
    }

    public function test_routes_are_absent_when_frontend_disabled(): void
    {
        config()->set('permission.frontend.enabled', false);

        $this->getJson('/api/authorization')->assertNotFound();
    }
}
