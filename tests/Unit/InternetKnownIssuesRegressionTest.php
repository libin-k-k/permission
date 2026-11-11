<?php

namespace Libinkk\Permission\Tests\Unit;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Libinkk\Permission\Authorization\AuthorizationContext;
use Libinkk\Permission\Authorization\DecisionReason;
use Libinkk\Permission\Contracts\PermissionCache;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Support\Tables;
use Libinkk\Permission\Tests\Fixtures\Organization;
use Libinkk\Permission\Tests\TestCase;

/**
 * Regression suite for well-known PHP / Laravel role-permission pitfalls
 * reported against Spatie laravel-permission and similar packages.
 *
 * Sources (community):
 * - https://github.com/spatie/laravel-permission/issues/2725 (stale wildcard after assignRole)
 * - https://github.com/spatie/laravel-permission/issues/2545 (Octane teamId leak)
 * - https://github.com/spatie/laravel-permission/discussions/2315 (soft-deleted permission still allows)
 * - https://github.com/spatie/laravel-permission/discussions/2323 (duplicate name+guard roles)
 * - https://github.com/spatie/laravel-permission/discussions/2479 (multi-guard assign mismatch)
 * - https://github.com/spatie/laravel-permission/pull/2574 (sync pivot duplicates)
 * - https://github.com/spatie/laravel-permission/pull/2965 (tenant/cache switch keeps in-memory state)
 * - https://spatie.be/docs/laravel-permission/v8/advanced-usage/cache (direct DB mutation stale cache)
 * - https://github.com/spatie/laravel-permission/blob/main/docs/basic-usage/teams-permissions.md (switch team without clearing)
 */
class InternetKnownIssuesRegressionTest extends TestCase
{
    /**
     * Spatie #2725: checking can() before assignRole left a stale wildcard index.
     */
    public function test_spatie_2725_wildcard_not_stale_after_assign_role(): void
    {
        $role = Role::findOrCreate('company-admin');
        Permission::findOrCreate('posts.*');
        Permission::findOrCreate('posts.delete');
        $role->givePermissionTo('posts.*');

        $user = $this->user();

        $this->assertFalse($user->can('posts.delete'), 'pre-check must be false');

        $user->assignRole($role);

        $this->assertTrue($user->hasRole('company-admin'));
        $this->assertTrue($user->can('posts.delete'), 'wildcard via role must apply after assign');
        $this->assertTrue($user->can('posts.create'));
    }

    /**
     * Spatie #2545: tenant / team context must not leak across request cycles (Octane).
     */
    public function test_spatie_2545_tenant_context_does_not_leak_after_flush(): void
    {
        config()->set('permission.teams.enabled', true);
        config()->set('permission.teams.require_context', true);

        $org = Organization::query()->create(['name' => 'Acme']);
        Role::findOrCreate('editor')->givePermissionTo('posts.create');
        $user = $this->user();
        $user->assignRole('editor', $org);

        AuthorizationContext::tenant($org);
        $this->assertTrue($user->can('posts.create'));

        // Simulate Octane / queue request end.
        app(PermissionCache::class)->flushRequestCache();
        AuthorizationContext::flush();

        $this->assertNull(AuthorizationContext::currentTenant());
        $decision = $user->authorizeFor('posts.create');
        $this->assertFalse($decision->allowed());
        $this->assertSame(DecisionReason::CONTEXT_MISSING, $decision->reason);
    }

    /**
     * Spatie discussion #2315: soft-deleted permission must not keep authorizing.
     */
    public function test_soft_deleted_permission_denies_even_with_pivot_left(): void
    {
        $user = $this->user();
        $permission = Permission::findOrCreate('billing.refund');
        $user->givePermissionTo($permission);
        $this->assertTrue($user->can('billing.refund'));

        $permission->delete();
        app(PermissionCache::class)->forgetUser($user);

        $this->assertFalse($user->can('billing.refund'));
        $this->assertSoftDeleted('permissions', ['id' => $permission->getKey()]);
    }

    /**
     * Soft-deleted role with pivot still present must not authorize (jeremykenedy / Spatie SoftDeletes cases).
     */
    public function test_soft_deleted_role_denies_even_with_user_role_pivot(): void
    {
        $role = Role::findOrCreate('temp-editor');
        $role->givePermissionTo('posts.update');
        $user = $this->user();
        $user->assignRole($role);
        $this->assertTrue($user->can('posts.update'));

        $role->delete();
        app(PermissionCache::class)->forgetUser($user);

        $this->assertFalse($user->can('posts.update'));
        $this->assertFalse($user->hasRole('temp-editor'));
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->getKey(),
            'role_id' => $role->getKey(),
        ]);
    }

    /**
     * Discussion #2323: same name + guard must not create duplicate role rows.
     */
    public function test_duplicate_role_name_guard_is_prevented(): void
    {
        $first = Role::findOrCreate('editor', 'web');
        $second = Role::findOrCreate('editor', 'web');

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Role::query()->where('name', 'editor')->where('guard_name', 'web')->count());

        $this->expectException(QueryException::class);
        Role::query()->create([
            'name' => 'editor',
            'slug' => 'editor-dup',
            'guard_name' => 'web',
            'scope_type' => 'global',
            'scope_id' => '',
        ]);
    }

    /**
     * Discussion #2479 / GuardDoesNotMatch: web user must not gain api-guard permission by name.
     */
    public function test_guard_mismatch_does_not_grant_cross_guard_permission(): void
    {
        $webUser = $this->user(['guard_name' => 'web']);
        $apiPermission = Permission::findOrCreate('posts.publish', 'api');
        $apiRole = Role::findOrCreate('publisher', 'api');
        $apiRole->givePermissionTo($apiPermission);

        $webUser->assignRole($apiRole);
        $webUser->givePermissionTo($apiPermission);

        $this->assertFalse($webUser->hasRole('publisher'));
        $this->assertFalse($webUser->can('posts.publish'));
    }

    /**
     * PR #2574: repeated sync must not leave duplicate pivot rows.
     */
    public function test_sync_permissions_does_not_duplicate_pivots(): void
    {
        $role = Role::findOrCreate('editor');
        Permission::findOrCreate('posts.view');
        Permission::findOrCreate('posts.create');

        $role->syncPermissions('posts.view', 'posts.create');
        $role->syncPermissions('posts.view', 'posts.create');
        $role->syncPermissions(['posts.view', 'posts.create']);

        $this->assertSame(2, DB::table(Tables::rolePermissions())->where('role_id', $role->getKey())->count());

        $user = $this->user();
        $user->syncRoles('editor');
        $user->syncRoles('editor');
        $user->givePermissionTo('posts.view');
        $user->givePermissionTo('posts.view');

        $this->assertSame(1, DB::table(Tables::userRoles())->where('user_id', $user->getKey())->count());
        $this->assertSame(1, DB::table(Tables::userPermissions())->where('user_id', $user->getKey())->count());
    }

    /**
     * Teams docs + PR #2965: switching tenant mid-request must not keep previous allow.
     */
    public function test_tenant_switch_invalidates_previous_allow(): void
    {
        config()->set('permission.teams.enabled', true);

        $orgA = Organization::query()->create(['name' => 'Acme']);
        $orgB = Organization::query()->create(['name' => 'Globex']);

        Role::findOrCreate('admin')->givePermissionTo('posts.delete');
        $user = $this->user();
        $user->assignRole('admin', $orgA);

        AuthorizationContext::tenant($orgA);
        $this->assertTrue($user->can('posts.delete'));

        AuthorizationContext::switch($orgB);
        $this->assertFalse($user->can('posts.delete'));
        $this->assertSame(DecisionReason::TENANT_MISMATCH, $user->authorizeFor('posts.delete')->reason);
    }

    /**
     * Spatie cache docs: package revoke path must clear stale allow without manual cache reset.
     */
    public function test_package_revoke_clears_stale_allow_without_manual_cache_reset(): void
    {
        $user = $this->user();
        $user->givePermissionTo('reports.export');
        $this->assertTrue($user->can('reports.export'));

        $user->revokePermissionTo('reports.export');

        $this->assertFalse($user->can('reports.export'));
    }

    /**
     * Spatie cache docs pitfall: raw DB detach used to leave stale allow.
     * watch_db_mutations bumps generation so the next can() reloads from DB.
     */
    public function test_raw_db_detach_is_picked_up_without_manual_forget(): void
    {
        $user = $this->user();
        $permission = Permission::findOrCreate('reports.import');
        $user->givePermissionTo($permission);
        $this->assertTrue($user->can('reports.import'));

        DB::table(Tables::userPermissions())
            ->where('user_type', $user->getMorphClass())
            ->where('user_id', $user->getKey())
            ->where('permission_id', $permission->getKey())
            ->delete();

        $this->assertFalse($user->can('reports.import'));
    }

    /**
     * Role permission grant after user already has the role (common cache miss).
     */
    public function test_role_permission_added_after_assign_is_visible(): void
    {
        $role = Role::findOrCreate('writer');
        $user = $this->user();
        $user->assignRole($role);

        $this->assertFalse($user->can('posts.publish'));

        $role->givePermissionTo('posts.publish');

        $this->assertTrue($user->can('posts.publish'));
    }

    /**
     * Exact deny must beat later wildcard allow on same user (precedence / privilege confusion).
     */
    public function test_explicit_deny_beats_later_wildcard_role_allow(): void
    {
        $user = $this->user();
        $user->denyPermissionTo('posts.delete');

        $role = Role::findOrCreate('super');
        $role->givePermissionTo('posts.*');
        $user->assignRole($role);

        $decision = $user->authorizeFor('posts.delete');
        $this->assertFalse($decision->allowed());
        $this->assertSame(DecisionReason::EXPLICIT_DENY, $decision->reason);
        $this->assertTrue($user->can('posts.create'));
    }

    /**
     * Missing tenant when teams.require_context is on must fail closed (tenant isolation footgun).
     */
    public function test_missing_tenant_context_fails_closed_when_required(): void
    {
        config()->set('permission.teams.enabled', true);
        config()->set('permission.teams.require_context', true);

        $user = $this->user();
        $user->givePermissionTo('posts.view');

        $decision = $user->authorizeFor('posts.view');
        $this->assertFalse($decision->allowed());
        $this->assertSame(DecisionReason::CONTEXT_MISSING, $decision->reason);
    }

    /**
     * ROLE_MISSING when a role in the system grants the ability but the user lacks that role.
     */
    public function test_role_missing_reason_when_role_grants_but_user_has_no_role(): void
    {
        Role::findOrCreate('editor')->givePermissionTo('posts.create');
        $user = $this->user();

        $decision = $user->authorizeFor('posts.create');
        $this->assertFalse($decision->allowed());
        $this->assertSame(DecisionReason::ROLE_MISSING, $decision->reason);
    }
}
