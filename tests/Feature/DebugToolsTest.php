<?php

namespace Libinkk\Permission\Tests\Feature;

use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\TestCase;

class DebugToolsTest extends TestCase
{
    public function test_graph_and_unused_commands(): void
    {
        Permission::crud('posts');
        Role::findOrCreate('admin')->givePermissionTo('posts.view');
        Role::inherit('admin', Role::findOrCreate('editor'));
        Permission::findOrCreate('legacy.unused');

        $this->artisan('permission:graph')
            ->assertSuccessful()
            ->expectsOutputToContain('admin');

        $this->artisan('permission:graph', ['--json' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('"hierarchy"');

        $this->artisan('permission:unused', ['--json' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('legacy.unused');
    }

    public function test_explain_command_for_allow_and_deny(): void
    {
        Permission::findOrCreate('invoice.approve');
        $user = $this->user(['name' => 'Jane']);
        $user->givePermissionTo('invoice.approve');

        $this->artisan('permission:explain', [
            'user' => $user->getKey(),
            'permission' => 'invoice.approve',
        ])->assertSuccessful()->expectsOutputToContain('ALLOWED');

        $this->artisan('permission:explain', [
            'user' => $user->getKey(),
            'permission' => 'invoice.reject',
            '--json' => true,
        ])->assertFailed()->expectsOutputToContain('DENIED');
    }

    public function test_explain_endpoint_is_off_by_default(): void
    {
        $this->actingAs($this->user());

        $this->getJson('/api/authorization/explain?permission=posts.view')
            ->assertNotFound();
    }
}
