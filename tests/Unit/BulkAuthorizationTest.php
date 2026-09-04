<?php

namespace Libinkk\Permission\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Tests\Fixtures\Organization;
use Libinkk\Permission\Tests\TestCase;

class BulkAuthorizationTest extends TestCase
{
    public function test_permissions_for_returns_resource_action_map(): void
    {
        Permission::crud('posts');
        $user = $this->user();
        $user->givePermissionTo('posts.view', 'posts.create');

        $map = $user->permissionsFor('posts');

        $this->assertTrue($map['view']);
        $this->assertTrue($map['create']);
        $this->assertFalse($map['update']);
        $this->assertFalse($map['delete']);
    }

    public function test_authorize_many_reuses_the_permission_map(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.update');

        $records = collect(range(1, 20))->map(
            fn (int $i) => Organization::query()->create(['name' => 'Post '.$i])
        );

        $user->preloadAuthorization();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $results = $user->authorizeMany('posts.update', $records);

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(20, $results);
        $this->assertTrue($results[0]['allowed']);
        $this->assertSame($records[0]->getKey(), $results[0]['resource']->getKey());
        $this->assertLessThan(40, $queries);
    }

    public function test_preload_then_can_does_not_query_again_for_same_map(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.view');
        $user->preloadAuthorization();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->assertTrue($user->can('posts.view'));
        $this->assertLessThan(8, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }
}
