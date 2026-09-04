<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Frontend\FrontendPayload;
use Libinkk\Permission\Frontend\PermissionMatrix;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\TestCase;

class FrontendPayloadTest extends TestCase
{
    public function test_payload_includes_roles_permissions_and_resources(): void
    {
        Permission::crud('posts');
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('posts.view', 'posts.create', 'posts.update');

        $user = $this->user();
        $user->assignRole($role);
        $user->denyPermissionTo('posts.delete');

        $payload = app(FrontendPayload::class)->for($user);

        $this->assertSame($user->getKey(), $payload['user']['id']);
        $this->assertContains('editor', $payload['roles']);
        $this->assertContains('posts.view', $payload['permissions']);
        $this->assertContains('posts.create', $payload['permissions']);
        $this->assertNotContains('posts.delete', $payload['permissions']);
        $this->assertContains('posts.delete', $payload['denials']);
        $this->assertTrue($payload['resources']['posts']['view']);
        $this->assertTrue($payload['resources']['posts']['create']);
        $this->assertFalse($payload['resources']['posts']['delete']);
        $this->assertArrayHasKey('tenant', $payload['scopes']);
    }

    public function test_guest_payload_is_empty_and_fail_closed(): void
    {
        $payload = app(FrontendPayload::class)->for(null);

        $this->assertNull($payload['user']);
        $this->assertSame([], $payload['roles']);
        $this->assertSame([], $payload['permissions']);
        $this->assertSame([], $payload['resources']);
    }

    public function test_wildcard_expands_into_resource_map(): void
    {
        Permission::crud('posts');
        $user = $this->user();
        $user->givePermissionTo('posts.*');

        $payload = app(FrontendPayload::class)->for($user);

        $this->assertContains('posts.*', $payload['permissions']);
        $this->assertTrue($payload['resources']['posts']['view']);
        $this->assertTrue($payload['resources']['posts']['delete']);
    }

    public function test_access_payload_includes_temporary_and_delegations(): void
    {
        $user = $this->user();
        $user->givePermissionTo('reports.export', expiresAt: now()->addDay());

        $access = app(FrontendPayload::class)->access($user);

        $this->assertContains('reports.export', $access['permissions']);
        $this->assertNotEmpty($access['temporary']);
        $this->assertArrayHasKey('received', $access['delegations']);
        $this->assertArrayHasKey('denials', $access);
    }

    public function test_permission_matrix_is_resource_oriented(): void
    {
        Permission::crud('posts');
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('posts.view', 'posts.create');
        $role->denyPermissionTo('posts.delete');

        $matrix = app(PermissionMatrix::class)->all();

        $this->assertTrue($matrix['roles']['editor']['posts']['view']);
        $this->assertTrue($matrix['roles']['editor']['posts']['create']);
        $this->assertFalse($matrix['roles']['editor']['posts']['update']);
        $this->assertFalse($matrix['roles']['editor']['posts']['delete']);
    }

    public function test_helper_and_blade_directive(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.create');
        $this->actingAs($user);

        $payload = permission_payload();
        $this->assertContains('posts.create', $payload['permissions']);

        $html = \Illuminate\Support\Facades\Blade::render('@permissionPayload');
        $this->assertStringContainsString('window.__LIBINKK_PERMISSION__', $html);
        $this->assertStringContainsString('posts.create', $html);
    }
}
