<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Authorization\AuthorizationContext;
use Libinkk\Permission\Authorization\DecisionReason;
use Libinkk\Permission\Exceptions\CircularScopeHierarchyException;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Scopes\Scope;
use Libinkk\Permission\Scopes\ScopeHierarchy;
use Libinkk\Permission\Tests\Fixtures\Organization;
use Libinkk\Permission\Tests\Fixtures\Workspace;
use Libinkk\Permission\Tests\TestCase;

class MultiTenancyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('permission.teams.enabled', true);
    }

    public function test_roles_are_isolated_by_tenant(): void
    {
        $orgA = Organization::query()->create(['name' => 'Acme']);
        $orgB = Organization::query()->create(['name' => 'Globex']);

        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('posts.create');

        $user = $this->user();
        $user->assignRole('editor', $orgA);

        AuthorizationContext::tenant($orgA);
        $this->assertCan($user, 'posts.create');
        $this->assertTrue($user->hasRole('editor'));

        AuthorizationContext::switch($orgB);
        $decision = $this->assertCannot($user, 'posts.create');
        $this->assertSame(DecisionReason::TENANT_MISMATCH, $decision->reason);
        $this->assertFalse($user->hasRole('editor'));
    }

    public function test_same_user_can_have_different_roles_per_tenant(): void
    {
        $orgA = Organization::query()->create(['name' => 'Acme']);
        $orgB = Organization::query()->create(['name' => 'Globex']);

        Role::findOrCreate('admin')->givePermissionTo('posts.delete');
        Role::findOrCreate('viewer')->givePermissionTo('posts.view');

        $user = $this->user();
        $user->assignRole('admin', $orgA);
        $user->assignRole('viewer', $orgB);

        AuthorizationContext::tenant($orgA);
        $this->assertCan($user, 'posts.delete');
        $this->assertCannot($user, 'posts.view');

        AuthorizationContext::switch($orgB);
        $this->assertCan($user, 'posts.view');
        $this->assertCannot($user, 'posts.delete');
    }

    public function test_parent_scope_assignment_applies_to_child(): void
    {
        $org = Organization::query()->create(['name' => 'Acme']);
        $workspace = Workspace::query()->create(['name' => 'Core', 'organization_id' => $org->id]);

        $orgScope = Scope::for($org, 'organization');
        Scope::for($workspace, 'workspace', $orgScope);

        Role::findOrCreate('editor')->givePermissionTo('posts.create');
        $user = $this->user();
        $user->assignRole('editor', $org);

        AuthorizationContext::scope($workspace);
        $this->assertCan($user, 'posts.create');
    }

    public function test_child_scope_assignment_does_not_apply_to_parent(): void
    {
        $org = Organization::query()->create(['name' => 'Acme']);
        $workspace = Workspace::query()->create(['name' => 'Core', 'organization_id' => $org->id]);

        $orgScope = Scope::for($org, 'organization');
        Scope::for($workspace, 'workspace', $orgScope);

        Role::findOrCreate('editor')->givePermissionTo('posts.create');
        $user = $this->user();
        $user->assignRole('editor', $workspace);

        AuthorizationContext::tenant($org);
        $this->assertCannot($user, 'posts.create');
    }

    public function test_global_roles_do_not_cross_tenants_by_default(): void
    {
        $org = Organization::query()->create(['name' => 'Acme']);
        Role::findOrCreate('super-admin')->givePermissionTo('posts.delete');

        $user = $this->user();
        $user->assignRole('super-admin');

        $this->assertCan($user, 'posts.delete');

        AuthorizationContext::tenant($org);
        $this->assertCannot($user, 'posts.delete');
    }

    public function test_global_roles_can_cross_tenants_when_allowed(): void
    {
        config()->set('permission.teams.global_roles.cross_tenant', true);

        $org = Organization::query()->create(['name' => 'Acme']);
        Role::findOrCreate('super-admin')->givePermissionTo('posts.delete');

        $user = $this->user();
        $user->assignRole('super-admin');

        AuthorizationContext::tenant($org);
        $this->assertCan($user, 'posts.delete');
    }

    public function test_require_context_fails_closed(): void
    {
        config()->set('permission.teams.require_context', true);

        Role::findOrCreate('editor')->givePermissionTo('posts.create');
        $user = $this->user();
        $user->assignRole('editor');

        $decision = $this->assertCannot($user, 'posts.create');
        $this->assertSame(DecisionReason::CONTEXT_MISSING, $decision->reason);
    }

    public function test_explain_includes_scope(): void
    {
        $org = Organization::query()->create(['name' => 'Acme']);
        Role::findOrCreate('editor')->givePermissionTo('posts.create');
        $user = $this->user();
        $user->assignRole('editor', $org);

        AuthorizationContext::tenant($org);
        $explain = $user->explain('posts.create');

        $this->assertTrue($explain['allowed']);
        $this->assertNotEmpty($explain['scope']);
    }

    public function test_circular_scope_hierarchy_is_rejected(): void
    {
        $org = Organization::query()->create(['name' => 'Acme']);
        $workspace = Workspace::query()->create(['name' => 'Core', 'organization_id' => $org->id]);

        $orgScope = Scope::for($org, 'organization');
        $wsScope = Scope::for($workspace, 'workspace', $orgScope);

        $this->expectException(CircularScopeHierarchyException::class);
        app(ScopeHierarchy::class)->setParent($orgScope, $wsScope);
    }

    public function test_export_includes_current_scope(): void
    {
        $org = Organization::query()->create(['name' => 'Acme']);
        $user = $this->user();
        $user->assignRole('editor', $org);

        AuthorizationContext::tenant($org);
        $export = $user->exportAccess();

        $this->assertNotEmpty($export['scope']);
    }
}
