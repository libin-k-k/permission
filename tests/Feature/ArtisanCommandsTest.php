<?php

namespace Libinkk\Permission\Tests\Feature;

use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\TestCase;

class ArtisanCommandsTest extends TestCase
{
    public function test_resource_command_creates_crud_permissions(): void
    {
        $this->artisan('permission:resource', ['name' => 'tags', '--crud' => true])
            ->assertSuccessful();

        $this->assertTrue(Permission::query()->where('name', 'tags.view')->exists());
        $this->assertTrue(Permission::query()->where('name', 'tags.delete')->exists());
    }

    public function test_validate_and_doctor_commands(): void
    {
        Role::findOrCreate('editor');
        Permission::crud('posts');

        $this->artisan('permission:validate')->assertSuccessful();
        $this->artisan('permission:doctor')->assertSuccessful();
    }

    public function test_cache_commands(): void
    {
        Permission::crud('posts');
        Role::findOrCreate('editor')->givePermissionTo('posts.view');

        $this->artisan('permission:cache')->assertSuccessful();
        $this->artisan('permission:cache:clear')->assertSuccessful();
    }

    public function test_discover_command(): void
    {
        $this->artisan('permission:discover', [
            '--path' => [dirname(__DIR__).'/Fixtures'],
        ])->assertSuccessful();
    }
}
