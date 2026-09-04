<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\TestCase;

class UserAccessExportTest extends TestCase
{
    public function test_export_access_includes_roles_permissions_and_totals(): void
    {
        $user = $this->user();

        Permission::defineResource('posts', ['view', 'create', 'update', 'delete'], ['group' => 'Posts']);
        Permission::findOrCreate('posts.*');

        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('posts.view', 'posts.create');
        $user->assignRole($role);
        $user->givePermissionTo('posts.*');

        $export = $user->exportAccess();

        $this->assertSame($user->getKey(), $export['user']['id']);
        $this->assertCount(1, $export['roles']);
        $this->assertSame('editor', $export['roles'][0]['slug']);
        $this->assertContains('posts.*', $export['direct_permissions']);
        $this->assertArrayHasKey('posts.view', $export['effective_permissions']);
        $this->assertArrayHasKey('posts.update', $export['effective_permissions']);
        $this->assertSame('wildcard:posts.*', $export['effective_permissions']['posts.update']['via']);
        $this->assertSame(1, $export['totals']['roles']);
        $this->assertGreaterThanOrEqual(1, $export['totals']['direct_permissions']);
        $this->assertGreaterThanOrEqual(4, $export['totals']['effective_permissions']);
        $this->assertArrayHasKey('Posts', $export['by_group']);
        $this->assertArrayHasKey('posts', $export['by_resource']);
        $this->assertArrayHasKey('temporary', $export);
        $this->assertArrayHasKey('delegations', $export);
        $this->assertNotEmpty($user->exportAccessJson());
    }

    public function test_export_command(): void
    {
        $user = $this->user();
        $user->givePermissionTo('reports.export');

        $this->artisan('permission:export', [
            'user' => $user->getKey(),
            '--type' => $user::class,
            '--format' => 'summary',
        ])->assertSuccessful();
    }
}
