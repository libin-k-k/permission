<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Authorization\DecisionReason;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\TestCase;

class TemporaryAccessTest extends TestCase
{
    public function test_named_expires_at_grants_until_deadline(): void
    {
        $user = $this->user();
        $user->givePermissionTo('reports.export', expiresAt: now()->addDays(7));

        $decision = $this->assertCan($user, 'reports.export');
        $this->assertSame('direct', $decision->source);

        $export = $user->exportAccess();
        $this->assertCount(1, $export['temporary']);
        $this->assertSame('reports.export', $export['temporary'][0]['name']);
        $this->assertTrue($export['temporary'][0]['active']);
    }

    public function test_expired_direct_permission_uses_expired_reason(): void
    {
        $user = $this->user();
        $user->givePermissionTo('reports.export', expiresAt: now()->subMinute());

        $decision = $this->assertCannot($user, 'reports.export');
        $this->assertSame(DecisionReason::EXPIRED_PERMISSION, $decision->reason);
    }

    public function test_future_starts_at_is_not_yet_active(): void
    {
        $user = $this->user();
        $user->givePermissionTo(
            'reports.export',
            startsAt: now()->addDay(),
            expiresAt: now()->addDays(8)
        );

        $this->assertCannot($user, 'reports.export');
    }

    public function test_expired_role_assignment_uses_expired_reason(): void
    {
        $user = $this->user();
        $role = Role::findOrCreate('contractor');
        $role->givePermissionTo('reports.export');
        $user->assignRole('contractor', expiresAt: now()->subMinute());

        $decision = $this->assertCannot($user, 'reports.export');
        $this->assertSame(DecisionReason::EXPIRED_PERMISSION, $decision->reason);
    }

    public function test_temporary_role_allows_while_active(): void
    {
        $user = $this->user();
        $role = Role::findOrCreate('contractor');
        $role->givePermissionTo('reports.export');
        $user->assignRole('contractor', expiresAt: now()->addHours(4));

        $this->assertCan($user, 'reports.export');
    }

    public function test_existing_expired_test_path_still_denies(): void
    {
        $user = $this->user();
        $permission = Permission::findOrCreate('posts.create');
        $user->givePermissionTo($permission);

        \Illuminate\Support\Facades\DB::table(\Libinkk\Permission\Support\Tables::userPermissions())
            ->where('user_id', $user->getKey())
            ->where('permission_id', $permission->getKey())
            ->update(['expires_at' => now()->subMinute()]);

        app(\Libinkk\Permission\Contracts\PermissionCache::class)->forgetUser($user);

        $decision = $this->assertCannot($user, 'posts.create');
        $this->assertSame(DecisionReason::EXPIRED_PERMISSION, $decision->reason);
    }
}
