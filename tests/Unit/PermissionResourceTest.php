<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Tests\TestCase;

class PermissionResourceTest extends TestCase
{
    public function test_define_resource_creates_actions(): void
    {
        $permissions = Permission::defineResource('posts', ['view', 'create', 'publish'], [
            'group' => 'Posts',
        ]);

        $this->assertCount(3, $permissions);
        $this->assertTrue(Permission::query()->where('name', 'posts.view')->exists());
        $this->assertTrue(Permission::query()->where('name', 'posts.publish')->exists());
        $this->assertSame('Posts', Permission::query()->where('name', 'posts.view')->value('group'));
        $this->assertSame('posts', Permission::query()->where('name', 'posts.view')->value('resource'));
        $this->assertSame('view', Permission::query()->where('name', 'posts.view')->value('action'));
    }

    public function test_crud_shortcut(): void
    {
        $permissions = Permission::crud('invoices');

        $this->assertCount(4, $permissions);
        $this->assertEqualsCanonicalizing(
            ['invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete'],
            $permissions->pluck('name')->all()
        );
        $this->assertSame('Invoices', $permissions->first()->group);
    }

    public function test_in_group(): void
    {
        Permission::defineResource('reports', ['view', 'export'], ['group' => 'Reports']);

        $this->assertCount(2, Permission::inGroup('Reports'));
    }
}
