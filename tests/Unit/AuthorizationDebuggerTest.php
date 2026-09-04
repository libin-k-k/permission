<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Authorization\DecisionReason;
use Libinkk\Permission\Debug\AuthorizationDebugger;
use Libinkk\Permission\Debug\DecisionRecorder;
use Libinkk\Permission\Filament\FilamentAuthorization;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Roles\Role;
use Libinkk\Permission\Tests\Fixtures\Organization;
use Libinkk\Permission\Tests\TestCase;

class AuthorizationDebuggerTest extends TestCase
{
    public function test_debug_report_explains_an_allow(): void
    {
        Permission::findOrCreate('invoice.approve');
        $role = Role::findOrCreate('finance-manager');
        $role->givePermissionTo('invoice.approve');

        $user = $this->user(['name' => 'John Doe']);
        $user->assignRole($role);
        $invoice = Organization::query()->create(['name' => 'Invoice']);

        $report = $user->debugAuthorization('invoice.approve', $invoice);

        $this->assertSame('ALLOWED', $report['final']);
        $this->assertSame(DecisionReason::ALLOWED, $report['reason']);
        $this->assertSame('invoice.approve', $report['action']);
        $this->assertStringContainsString('John Doe', $report['user']['label']);
        $this->assertStringContainsString('Organization', $report['resource']['label']);
        $this->assertTrue($report['permission']['passed']);
        $this->assertStringContainsString('FINAL DECISION:', $report['text']);
        $this->assertStringContainsString('ALLOWED', $report['text']);
        $this->assertNotEmpty($report['checks']);
    }

    public function test_debug_report_explains_a_deny(): void
    {
        Permission::findOrCreate('invoice.approve');
        $user = $this->user();

        $report = app(AuthorizationDebugger::class)->debug($user, 'invoice.approve');

        $this->assertSame('DENIED', $report['final']);
        $this->assertSame(DecisionReason::PERMISSION_MISSING, $report['reason']);
        $this->assertFalse($report['permission']['passed']);
    }

    public function test_debug_is_not_a_grant(): void
    {
        Permission::findOrCreate('posts.delete');
        $user = $this->user();

        $report = $user->debugAuthorization('posts.delete');

        $this->assertSame('DENIED', $report['final']);
        $this->assertFalse($user->can('posts.delete'));
    }

    public function test_filament_debug_fails_closed_without_user(): void
    {
        $report = FilamentAuthorization::debug('posts.view');

        $this->assertSame('DENIED', $report['final']);
        $this->assertSame('CONTEXT_MISSING', $report['reason']);
    }

    public function test_decision_recorder_stays_empty_when_disabled(): void
    {
        $user = $this->user();
        $user->givePermissionTo('posts.view');
        $user->can('posts.view');

        $this->assertSame([], app(DecisionRecorder::class)->all());
    }

    public function test_decision_recorder_captures_when_enabled(): void
    {
        config()->set('permission.debug.record_decisions', true);

        $user = $this->user();
        $user->givePermissionTo('posts.view');
        $user->can('posts.view');
        $user->can('posts.delete');

        $entries = app(DecisionRecorder::class)->all();

        $this->assertCount(2, $entries);
        $this->assertTrue($entries[0]['allowed']);
        $this->assertFalse($entries[1]['allowed']);
    }
}
