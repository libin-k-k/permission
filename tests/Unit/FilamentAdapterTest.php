<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Authorization\AuthorizationContext;
use Libinkk\Permission\Filament\FilamentAuthorization;
use Libinkk\Permission\Filament\PermissionFilamentPlugin;
use Libinkk\Permission\Filament\SyncFilamentTenantContext;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Tests\Fixtures\Organization;
use Libinkk\Permission\Tests\Fixtures\PostResourceStub;
use Libinkk\Permission\Tests\Fixtures\ReportsPageStub;
use Libinkk\Permission\Tests\Fixtures\ReportsWidgetStub;
use Libinkk\Permission\Tests\Fixtures\UsersRelationManagerStub;
use Libinkk\Permission\Tests\TestCase;

class FilamentAdapterTest extends TestCase
{
    public function test_resource_maps_crud_abilities(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.view', 'posts.create', 'posts.update');
        $this->actingAs($user);

        $record = Organization::query()->create(['name' => 'Post']);

        $this->assertTrue(PostResourceStub::canViewAny());
        $this->assertTrue(PostResourceStub::canCreate());
        $this->assertTrue(PostResourceStub::canEdit($record));
        $this->assertTrue(PostResourceStub::canView($record));
        $this->assertFalse(PostResourceStub::canDelete($record));
        $this->assertFalse(PostResourceStub::canDeleteAny());
        $this->assertTrue(PostResourceStub::shouldRegisterNavigation());
        $this->assertSame('posts.delete', PostResourceStub::permissionFor('delete'));
    }

    public function test_resource_fails_closed_without_user(): void
    {
        $this->assertFalse(PostResourceStub::canViewAny());
        $this->assertFalse(PostResourceStub::canCreate());
    }

    public function test_page_and_widget_and_navigation(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->assertFalse(ReportsPageStub::canAccess());
        $this->assertFalse(ReportsWidgetStub::canView());

        $user->givePermissionTo('reports.view', 'dashboard.reports.view');

        $this->assertTrue(ReportsPageStub::canAccess());
        $this->assertTrue(ReportsPageStub::shouldRegisterNavigation());
        $this->assertTrue(ReportsWidgetStub::canView());
    }

    public function test_relation_manager_abilities(): void
    {
        $user = $this->user();
        $owner = Organization::query()->create(['name' => 'Org']);
        $user->givePermissionTo('users.view', 'users.attach', 'users.detach');
        $this->actingAs($user);

        $manager = new UsersRelationManagerStub;

        $this->assertTrue(UsersRelationManagerStub::canViewForRecord($owner, 'index'));
        $this->assertTrue($manager->canAttach());
        $this->assertTrue($manager->canDetach());
        $this->assertFalse($manager->canCreate());
        $this->assertFalse($manager->canAssociate());
    }

    public function test_form_and_table_closures(): void
    {
        $user = $this->user();
        $record = Organization::query()->create(['name' => 'Employee']);
        $user->givePermissionTo('employees.salary.view');
        $this->actingAs($user);

        $this->assertTrue((FilamentAuthorization::visible('employees.salary.view'))($record));
        $this->assertTrue((FilamentAuthorization::disabled('employees.salary.update'))($record));
        $this->assertTrue((FilamentAuthorization::navigation('employees.salary.view'))());
        $this->assertFalse((FilamentAuthorization::navigation('employees.salary.update'))());
    }

    public function test_bulk_all_versus_partial(): void
    {
        $user = $this->user();
        $allowed = Organization::query()->create(['name' => 'A']);
        $denied = Organization::query()->create(['name' => 'B']);

        Permission::define('posts.delete')->when(
            fn ($actor, $org) => is_object($org) && ($org->name ?? null) === 'A'
        );
        $user->givePermissionTo('posts.delete');
        $this->actingAs($user);

        $this->assertTrue(FilamentAuthorization::allows('posts.delete', $allowed));
        $this->assertFalse(FilamentAuthorization::allows('posts.delete', $denied));

        $this->assertFalse(FilamentAuthorization::bulk('posts.delete', [$allowed, $denied], 'all'));
        $this->assertTrue(FilamentAuthorization::bulk('posts.delete', [$allowed, $denied], 'any'));
        $this->assertTrue(FilamentAuthorization::bulk('posts.delete', [$allowed], 'all'));

        $breakdown = FilamentAuthorization::bulkBreakdown('posts.delete', [$allowed, $denied]);
        $this->assertTrue($breakdown['partial']);
        $this->assertSame(1, $breakdown['allowed']);
        $this->assertSame(1, $breakdown['denied']);

        $this->assertFalse((FilamentAuthorization::bulkCallback('posts.delete', 'all'))([$allowed, $denied]));
        $this->assertCount(1, FilamentAuthorization::authorizedRecords('posts.delete', [$allowed, $denied]));
    }

    public function test_guess_resource_from_class_name(): void
    {
        $this->assertSame('posts', FilamentAuthorization::guessResource('App\\Filament\\Resources\\PostResource'));
        $this->assertSame('users.attach', FilamentAuthorization::permission('users', 'attach'));
    }

    public function test_tenant_sync_is_noop_without_filament(): void
    {
        config()->set('permission.filament.enabled', true);

        $org = Organization::query()->create(['name' => 'Tenant']);
        $sync = new SyncFilamentTenantContext;

        $this->assertNull($sync->currentTenant());
        $sync->sync();
        $this->assertNull(AuthorizationContext::currentTarget());

        AuthorizationContext::tenant($org);
        $this->assertSame($org, AuthorizationContext::currentTarget());
        AuthorizationContext::flush();
    }

    public function test_plugin_exposes_middleware_without_filament_types(): void
    {
        $plugin = PermissionFilamentPlugin::make();

        $this->assertSame('libinkk-permission', $plugin->getId());
        $this->assertSame([SyncFilamentTenantContext::class], PermissionFilamentPlugin::middleware());
    }
}
