<?php

namespace Libinkk\Permission\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Libinkk\Permission\Authorization\DecisionReason;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Support\Tables;
use Libinkk\Permission\Tests\TestCase;

class AuthorizationEngineTest extends TestCase
{
    public function test_missing_permission_is_denied(): void
    {
        $user = $this->user();

        $decision = $this->assertCannot($user, 'posts.create');

        $this->assertSame(DecisionReason::PERMISSION_MISSING, $decision->reason);
        $this->assertFalse($user->can('posts.create'));
    }

    public function test_role_permission_allows(): void
    {
        $user = $this->user();
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('posts.create');
        $user->assignRole($role);

        $decision = $this->assertCan($user, 'posts.create');

        $this->assertSame('role:editor', $decision->source);
        $this->assertTrue($user->can('posts.create'));
        $this->assertTrue($user->hasRole('editor'));
    }

    public function test_direct_permission_allows(): void
    {
        $user = $this->user();
        $user->givePermissionTo('reports.export');

        $decision = $this->assertCan($user, 'reports.export');

        $this->assertSame('direct', $decision->source);
        $this->assertTrue($user->hasPermissionTo('reports.export'));
    }

    public function test_direct_permission_can_be_revoked(): void
    {
        $user = $this->user();
        $user->givePermissionTo('reports.export');
        $user->revokePermissionTo('reports.export');

        $this->assertCannot($user, 'reports.export');
    }

    public function test_role_can_be_removed(): void
    {
        $user = $this->user();
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('posts.create');
        $user->assignRole('editor');
        $user->removeRole('editor');

        $this->assertCannot($user, 'posts.create');
        $this->assertFalse($user->hasRole('editor'));
    }

    public function test_inactive_permission_is_denied(): void
    {
        $user = $this->user();
        $permission = Permission::findOrCreate('posts.create');
        $permission->forceFill(['is_active' => false])->save();
        $user->givePermissionTo($permission);

        $this->assertCannot($user, 'posts.create');
    }

    public function test_expired_direct_permission_is_denied(): void
    {
        $user = $this->user();
        $permission = Permission::findOrCreate('posts.create');
        $user->givePermissionTo($permission);

        DB::table(Tables::userPermissions())
            ->where('user_id', $user->getKey())
            ->where('permission_id', $permission->getKey())
            ->update(['expires_at' => now()->subMinute()]);

        app(\Libinkk\Permission\Contracts\PermissionCache::class)->forgetUser($user);

        $this->assertCannot($user, 'posts.create');
    }

    public function test_can_any_and_can_all(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.create');

        $this->assertTrue($user->canAny(['posts.create', 'posts.delete']));
        $this->assertFalse($user->canAll(['posts.create', 'posts.delete']));

        $user->givePermissionTo('posts.delete');

        $this->assertTrue($user->canAll(['posts.create', 'posts.delete']));
    }

    public function test_authorize_for_returns_decision(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.update');

        $decision = $user->authorizeFor('posts.update');

        $this->assertTrue($decision->allowed());
        $this->assertSame('posts.update', $decision->permission);
        $this->assertSame('ALLOWED', $user->explain('posts.update')['reason']);
    }

    public function test_engine_failure_fails_closed(): void
    {
        $user = $this->user();
        $engine = app(\Libinkk\Permission\Contracts\AuthorizationEngine::class);

        $decision = $engine->decide($user, 'posts.create');

        $this->assertTrue($decision->denied());
        $this->assertSame(DecisionReason::PERMISSION_MISSING, $decision->reason);
    }
}
