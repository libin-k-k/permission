<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Audit\AuthorizationAudit;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Permissions\PermissionVersion;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\TestCase;

class AuditVersioningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('permission.audit.enabled', true);
        config()->set('permission.audit.decisions', true);
    }

    public function test_assignment_events_are_audited(): void
    {
        $user = $this->user();
        $role = Role::findOrCreate('editor');
        $user->assignRole($role);
        $user->givePermissionTo('reports.export');
        $user->revokePermissionTo('reports.export');
        $user->removeRole($role);

        $events = AuthorizationAudit::query()->pluck('reason')->all();

        $this->assertContains('role.assigned', $events);
        $this->assertContains('permission.granted', $events);
        $this->assertContains('permission.revoked', $events);
        $this->assertContains('role.removed', $events);
    }

    public function test_decisions_are_audited_when_enabled(): void
    {
        $user = $this->user();
        $user->givePermissionTo('reports.export');
        $user->can('reports.export');
        $user->can('posts.delete');

        $results = AuthorizationAudit::query()
            ->whereIn('reason', ['authorization.allowed', 'authorization.denied'])
            ->pluck('result')
            ->all();

        $this->assertContains('allowed', $results);
        $this->assertContains('denied', $results);
    }

    public function test_delegation_is_audited(): void
    {
        $owner = $this->user();
        $manager = $this->user();
        $owner->givePermissionTo('invoice.approve');
        $delegation = $owner->delegate('invoice.approve', to: $manager, until: now()->addHour());
        $owner->revokeDelegation($delegation);

        $events = AuthorizationAudit::query()->pluck('reason')->all();

        $this->assertContains('delegation.created', $events);
        $this->assertContains('delegation.revoked', $events);
    }

    public function test_permission_versions_and_rollback(): void
    {
        $permission = Permission::findOrCreate('reports.export', attributes: [
            'description' => 'Export reports',
            'risk_level' => 'LOW',
        ]);

        $this->assertSame(1, $permission->versions()->count());

        $permission->description = 'Export reports (finance)';
        $permission->risk_level = 'HIGH';
        $permission->save();

        $this->assertSame(2, $permission->versions()->count());
        $this->assertSame('HIGH', $permission->fresh()->risk_level);

        $permission->rollbackTo(1, 'revert risk');

        $this->assertSame('LOW', $permission->fresh()->risk_level);
        $this->assertSame('Export reports', $permission->fresh()->description);
        $this->assertGreaterThanOrEqual(3, $permission->versions()->count());

        $history = $permission->history();
        $this->assertSame('reports.export', $history['permission']);
        $this->assertNotEmpty($history['versions']);
        $this->assertContains('policy.changed', AuthorizationAudit::query()->pluck('reason')->all());
    }

    public function test_audit_rows_are_not_soft_deleted(): void
    {
        $user = $this->user();
        $user->givePermissionTo('reports.export');

        $before = AuthorizationAudit::query()->count();
        $this->assertGreaterThan(0, $before);

        $audit = AuthorizationAudit::query()->first();
        $this->assertNotNull($audit);
        $this->assertFalse($audit->delete());
        $this->assertSame($before, AuthorizationAudit::query()->count());
    }

    public function test_version_snapshot_exists_for_new_permissions(): void
    {
        Permission::findOrCreate('invoices.approve');

        $this->assertTrue(PermissionVersion::query()->where('change_reason', 'created')->exists());
    }
}
