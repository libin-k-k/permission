<?php

namespace Libinkk\Permission\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Libinkk\Permission\Contracts\PermissionCache;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\TestCase;

class PermissionCacheTest extends TestCase
{
    public function test_repeated_checks_use_request_cache(): void
    {
        $user = $this->user();
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('posts.create');
        $user->assignRole($role);

        $this->assertTrue($user->can('posts.create'));

        DB::table(\Libinkk\Permission\Support\Tables::rolePermissions())->delete();

        $this->assertTrue($user->can('posts.create'));
    }

    public function test_assignment_invalidates_user_cache(): void
    {
        $user = $this->user();
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('posts.create');

        $this->assertFalse($user->can('posts.create'));

        $user->assignRole($role);

        $this->assertTrue($user->can('posts.create'));
    }

    public function test_role_permission_change_invalidates_cache(): void
    {
        $user = $this->user();
        $role = Role::findOrCreate('editor');
        $user->assignRole($role);

        $this->assertFalse($user->can('posts.update'));

        $role->givePermissionTo('posts.update');

        $this->assertTrue($user->can('posts.update'));
    }

    public function test_cache_prefix_matches_spec(): void
    {
        $prefix = app(PermissionCache::class)->prefix();

        $this->assertSame('libinkk:permission:v1', $prefix);
        $this->assertSame('array', config('cache.default'));
        $this->assertInstanceOf(\Illuminate\Cache\ArrayStore::class, Cache::getStore());
    }
}
