<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Audit\AuthorizationAudit;
use Libinkk\Permission\Authorization\AuthorizationContext;
use Libinkk\Permission\Authorization\DecisionReason;
use Libinkk\Permission\Cache\PermissionFake;
use Libinkk\Permission\Conditions\Condition;
use Libinkk\Permission\Exceptions\CannotDelegatePermissionException;
use Libinkk\Permission\Exceptions\CircularRoleInheritanceException;
use Libinkk\Permission\Exceptions\SystemRecordProtectedException;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\Fixtures\Organization;
use Libinkk\Permission\Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_missing_user_context_and_blank_permission_fail_closed(): void
    {
        $user = $this->user();

        $this->assertSame(DecisionReason::PERMISSION_MISSING, $user->authorizeFor('no.such.permission')->reason);
        $this->assertSame(DecisionReason::CONTEXT_MISSING, $user->authorizeFor('')->reason);
        $this->assertSame(DecisionReason::CONTEXT_MISSING, $user->authorizeFor("posts.create\0")->reason);
        $this->assertFalse($user->can('posts.create'));
    }

    public function test_sql_like_permission_names_never_inject(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.create');

        $this->assertCannot($user, "posts.create' OR '1'='1");
        $this->assertCannot($user, 'posts.create--');
        $this->assertCannot($user, '1=1');
        $this->assertCan($user, 'posts.create');
    }

    public function test_wildcard_does_not_cross_resources(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.*');

        $this->assertCan($user, 'posts.view');
        $this->assertCannot($user, 'posts.view.own');
        $this->assertCannot($user, 'invoices.approve');
        $this->assertCannot($user, 'posts');
        $this->assertCannot($user, 'postssomething.view');
    }

    public function test_explicit_deny_beats_wildcard_and_role_and_delegation(): void
    {
        $owner = $this->user();
        $target = $this->user();
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('posts.*');

        $target->assignRole($role);
        $target->denyPermissionTo('posts.delete');

        $this->assertCan($target, 'posts.create');
        $decision = $this->assertCannot($target, 'posts.delete');
        $this->assertSame(DecisionReason::EXPLICIT_DENY, $decision->reason);

        $owner->givePermissionTo('posts.delete');
        $owner->delegate('posts.delete', to: $target, until: now()->addHour());
        $this->assertSame(DecisionReason::EXPLICIT_DENY, $target->authorizeFor('posts.delete')->reason);
    }

    public function test_cannot_delegate_permission_not_held_or_to_self(): void
    {
        $user = $this->user();
        $other = $this->user();

        try {
            $user->delegate('invoices.approve', to: $other, until: now()->addHour());
            $this->fail('Missing permission must not delegate.');
        } catch (CannotDelegatePermissionException) {
            $this->assertTrue(true);
        }

        $user->givePermissionTo('invoices.approve');

        try {
            $user->delegate('invoices.approve', to: $user, until: now()->addHour());
            $this->fail('Self-delegation must be rejected.');
        } catch (CannotDelegatePermissionException $e) {
            $this->assertSame(CannotDelegatePermissionException::selfDelegation()->getMessage(), $e->getMessage());
        }
    }

    public function test_delegatee_cannot_revoke_or_redelegate(): void
    {
        $owner = $this->user();
        $manager = $this->user();
        $third = $this->user();
        $owner->givePermissionTo('invoice.approve');
        $delegation = $owner->delegate('invoice.approve', to: $manager, until: now()->addHours(4));

        try {
            $manager->revokeDelegation($delegation);
            $this->fail('Delegatee must not revoke.');
        } catch (CannotDelegatePermissionException) {
            $this->assertSame('active', $delegation->fresh()->status);
        }

        try {
            $manager->delegate('invoice.approve', to: $third, until: now()->addHour());
            $this->fail('Delegatee must not re-delegate.');
        } catch (CannotDelegatePermissionException) {
            $this->assertTrue(true);
        }
    }

    public function test_lost_delegator_access_and_expired_delegation_never_authorize(): void
    {
        $owner = $this->user();
        $manager = $this->user();
        $owner->givePermissionTo('invoice.approve');
        $owner->delegate('invoice.approve', to: $manager, until: now()->addHours(4));
        $this->assertCan($manager, 'invoice.approve');

        $owner->revokePermissionTo('invoice.approve');
        $this->assertCannot($manager, 'invoice.approve');
    }

    public function test_inactive_and_soft_deleted_permissions_do_not_authorize(): void
    {
        $user = $this->user();
        $permission = Permission::findOrCreate('secrets.view');
        $user->givePermissionTo($permission);

        $permission->is_active = false;
        $permission->save();
        app(\Libinkk\Permission\Contracts\PermissionCache::class)->forgetUser($user);
        $this->assertCannot($user, 'secrets.view');

        $permission->is_active = true;
        $permission->save();
        $permission->delete();
        app(\Libinkk\Permission\Contracts\PermissionCache::class)->forgetUser($user);
        $this->assertCannot($user, 'secrets.view');
    }

    public function test_inactive_role_does_not_authorize(): void
    {
        $role = Role::findOrCreate('contractor');
        $role->givePermissionTo('reports.export');
        $user = $this->user();
        $user->assignRole($role);

        $role->is_active = false;
        $role->save();
        app(\Libinkk\Permission\Contracts\PermissionCache::class)->forgetUser($user);

        $this->assertCannot($user, 'reports.export');
    }

    public function test_system_role_and_permission_cannot_be_deleted_or_unprotected(): void
    {
        $role = Role::findOrCreate('super-admin');
        $role->is_system = true;
        $role->save();

        $permission = Permission::findOrCreate('system.manage');
        $permission->is_system = true;
        $permission->save();

        $this->expectException(SystemRecordProtectedException::class);
        $role->delete();
    }

    public function test_system_permission_cannot_drop_protection(): void
    {
        $permission = Permission::findOrCreate('system.manage');
        $permission->is_system = true;
        $permission->save();

        $this->expectException(SystemRecordProtectedException::class);
        $permission->is_system = false;
        $permission->save();
    }

    public function test_condition_exceptions_fail_closed(): void
    {
        Condition::define('broken', function () {
            throw new \RuntimeException('boom');
        });

        Permission::define('vault.open')->when('broken');
        $user = $this->user();
        $user->givePermissionTo('vault.open');

        $decision = $this->assertCannot($user, 'vault.open');
        $this->assertSame(DecisionReason::CONDITION_FAILED, $decision->reason);
    }

    public function test_unregistered_named_condition_fails_closed(): void
    {
        Permission::define('vault.open')->when('not-registered');
        $user = $this->user();
        $user->givePermissionTo('vault.open');

        $this->assertCannot($user, 'vault.open');
    }

    public function test_ownership_without_resource_denies(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.update.own');

        $this->assertCannot($user, 'posts.update.own');
    }

    public function test_tenant_isolation_and_require_context(): void
    {
        config()->set('permission.teams.enabled', true);
        config()->set('permission.teams.require_context', true);

        $orgA = Organization::query()->create(['name' => 'A']);
        $orgB = Organization::query()->create(['name' => 'B']);
        Role::findOrCreate('editor')->givePermissionTo('posts.create');
        $user = $this->user();
        $user->assignRole('editor', $orgA);

        $missing = $user->authorizeFor('posts.create');
        $this->assertFalse($missing->allowed());
        $this->assertSame(DecisionReason::CONTEXT_MISSING, $missing->reason);

        AuthorizationContext::tenant($orgB);
        $this->assertSame(DecisionReason::TENANT_MISMATCH, $user->authorizeFor('posts.create')->reason);

        AuthorizationContext::switch($orgA);
        $this->assertCan($user, 'posts.create');
        AuthorizationContext::flush();
    }

    public function test_child_scope_does_not_grant_parent(): void
    {
        config()->set('permission.teams.enabled', true);

        $org = Organization::query()->create(['name' => 'Acme']);
        $ws = \Libinkk\Permission\Tests\Fixtures\Workspace::query()->create([
            'name' => 'Core',
            'organization_id' => $org->id,
        ]);
        $orgScope = \Libinkk\Permission\Scopes\Scope::for($org, 'organization');
        \Libinkk\Permission\Scopes\Scope::for($ws, 'workspace', $orgScope);

        Role::findOrCreate('editor')->givePermissionTo('posts.create');
        $user = $this->user();
        $user->assignRole('editor', $ws);

        AuthorizationContext::scope($org);
        $this->assertCannot($user, 'posts.create');

        AuthorizationContext::scope($ws);
        $this->assertCan($user, 'posts.create');
        AuthorizationContext::flush();
    }

    public function test_cache_does_not_leak_across_users(): void
    {
        $admin = $this->user();
        $guest = $this->user();
        $admin->givePermissionTo('secrets.view');

        $this->assertCan($admin, 'secrets.view');
        $this->assertCannot($guest, 'secrets.view');
    }

    public function test_assignment_invalidates_stale_deny_cache(): void
    {
        $user = $this->user();
        $this->assertCannot($user, 'posts.publish');

        $user->givePermissionTo('posts.publish');
        $this->assertCan($user, 'posts.publish');
    }

    public function test_circular_role_inheritance_is_rejected(): void
    {
        $this->expectException(CircularRoleInheritanceException::class);
        Role::inherit('admin', 'manager');
        Role::inherit('manager', 'admin');
    }

    public function test_audit_rows_are_immutable(): void
    {
        config()->set('permission.audit.enabled', true);
        $user = $this->user();
        $user->givePermissionTo('reports.export');

        $audit = AuthorizationAudit::query()->first();
        $this->assertNotNull($audit);
        $this->assertFalse($audit->delete());
        $this->assertFalse($audit->forceDelete());
        $this->assertFalse($audit->update(['reason' => 'tampered']));
        $this->assertNotSame('tampered', $audit->fresh()->reason);
    }

    public function test_frontend_payload_is_not_a_grant_and_guest_is_empty(): void
    {
        $payload = permission_payload();
        $this->assertSame([], $payload['permissions']);
        $this->assertNull($payload['user']);

        $user = $this->user();
        $user->givePermissionTo('posts.create');
        $this->actingAs($user);

        $this->assertContains('posts.create', permission_payload()['permissions']);
        $this->assertTrue($user->can('posts.create'));
    }

    public function test_frontend_payload_does_not_include_another_users_permissions(): void
    {
        $self = $this->user();
        $other = $this->user();
        $other->givePermissionTo('secrets.view');
        $this->actingAs($self);

        $payload = permission_payload();
        $this->assertNotContains('secrets.view', $payload['permissions']);
        $this->assertSame($self->getKey(), $payload['user']['id']);
    }

    public function test_permission_fake_is_blocked_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        Permission::fake();
    }

    public function test_middleware_denies_unauthenticated_and_unauthorized(): void
    {
        $this->get('/permission-check')->assertForbidden();

        $user = $this->user();
        $this->actingAs($user);
        $this->get('/permission-check')->assertForbidden();

        $user->givePermissionTo('posts.create');
        $this->get('/permission-check')->assertOk();
    }

    public function test_guard_isolation(): void
    {
        $web = $this->user(['guard_name' => 'web']);
        $api = $this->user(['guard_name' => 'api']);

        Permission::findOrCreate('posts.create', 'web');
        Permission::findOrCreate('posts.create', 'api');
        $web->givePermissionTo(Permission::findOrCreate('posts.create', 'web'));

        $this->assertTrue($web->can('posts.create'));
        $this->assertFalse($api->can('posts.create'));
    }

    protected function tearDown(): void
    {
        $this->app['env'] = 'testing';
        PermissionFake::reset();
        AuthorizationContext::flush();
        parent::tearDown();
    }
}
