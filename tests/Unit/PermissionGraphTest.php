<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Debug\PermissionGraph;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\TestCase;

class PermissionGraphTest extends TestCase
{
    public function test_graph_nests_inherited_roles_and_lists_permissions(): void
    {
        Permission::crud('posts');
        Permission::findOrCreate('reports.view');

        $admin = Role::findOrCreate('admin');
        $manager = Role::findOrCreate('manager');
        $editor = Role::findOrCreate('editor');

        Role::inherit('admin', 'manager');
        Role::inherit('manager', 'editor');

        $admin->givePermissionTo('posts.*', 'reports.view');
        $editor->givePermissionTo('posts.view', 'posts.create');

        $graph = app(PermissionGraph::class)->build();

        $this->assertSame('admin', $graph['hierarchy'][0]['slug']);
        $this->assertSame('manager', $graph['hierarchy'][0]['children'][0]['slug']);
        $this->assertSame('editor', $graph['hierarchy'][0]['children'][0]['children'][0]['slug']);
        $this->assertContains('reports.view', $graph['permissions']['admin']);
        $this->assertContains('posts.view', $graph['permissions']['editor']);
    }

    public function test_graph_text_and_json_are_self_describing(): void
    {
        Role::findOrCreate('admin')->givePermissionTo('posts.view');
        Permission::findOrCreate('posts.view');

        $service = app(PermissionGraph::class);
        $graph = $service->build();
        $text = $service->toText($graph);
        $json = json_decode($service->toJson($graph), true);

        $this->assertStringContainsString('admin', $text);
        $this->assertStringContainsString('posts.view', $text);
        $this->assertSame('web', $json['guard']);
        $this->assertArrayHasKey('hierarchy', $json);
        $this->assertArrayHasKey('permissions', $json);
    }
}
