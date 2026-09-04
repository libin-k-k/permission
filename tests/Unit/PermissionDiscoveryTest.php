<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Discovery\PermissionDiscovery;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Tests\TestCase;

class PermissionDiscoveryTest extends TestCase
{
    public function test_discovers_attributes_from_fixture(): void
    {
        $discovery = app(PermissionDiscovery::class);
        $path = dirname(__DIR__).'/Fixtures';

        $found = $discovery->discover([$path]);

        $names = $found->pluck('name')->all();

        $this->assertContains('posts.publish', $names);
        $this->assertContains('posts.feature', $names);

        $publish = $found->firstWhere('name', 'posts.publish');
        $this->assertSame('Posts', $publish['group']);
        $this->assertSame('Publish posts', $publish['description']);
    }

    public function test_sync_creates_missing_permissions(): void
    {
        $discovery = app(PermissionDiscovery::class);
        $path = dirname(__DIR__).'/Fixtures';

        $result = $discovery->sync([$path]);

        $this->assertContains('posts.publish', $result['created']);
        $this->assertTrue(Permission::query()->where('name', 'posts.publish')->exists());

        $second = $discovery->sync([$path]);
        $this->assertContains('posts.publish', $second['existing']);
        $this->assertSame([], $second['created']);
    }
}
