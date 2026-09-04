<?php

namespace Libinkk\Permission\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Libinkk\Permission\Cache\CacheMetrics;
use Libinkk\Permission\Contracts\PermissionCache;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\TestCase;

class CacheLayerTest extends TestCase
{
    public function test_request_cache_records_l1_hits(): void
    {
        $user = $this->user();
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('posts.create');
        $user->assignRole($role);

        $this->assertTrue($user->can('posts.create'));
        $this->assertTrue($user->can('posts.create'));

        $snapshot = app(CacheMetrics::class)->snapshot();
        $this->assertGreaterThan(0, $snapshot['l1_hits'] + $snapshot['l2_hits']);
    }

    public function test_redis_l3_opt_in_does_not_break_when_store_matches_l2(): void
    {
        config()->set('permission.cache.redis.enabled', true);
        config()->set('permission.cache.redis.store', 'array');
        config()->set('permission.cache.store', 'array');

        $user = $this->user();
        $user->givePermissionTo('reports.export');

        $this->assertTrue($user->can('reports.export'));
        $this->assertSame('array', config('cache.default'));
        $this->assertInstanceOf(\Illuminate\Cache\ArrayStore::class, Cache::getStore());
    }

    public function test_generation_bump_invalidates_stale_decisions(): void
    {
        $user = $this->user();
        $this->assertFalse($user->can('posts.publish'));

        $user->givePermissionTo('posts.publish');

        $this->assertTrue($user->can('posts.publish'));
        app(PermissionCache::class)->forgetUser($user);
        $user->revokePermissionTo('posts.publish');
        $this->assertFalse($user->can('posts.publish'));
    }
}
