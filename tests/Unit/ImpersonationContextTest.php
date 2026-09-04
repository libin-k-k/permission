<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Authorization\AuthorizationContext;
use Libinkk\Permission\Tests\TestCase;

class ImpersonationContextTest extends TestCase
{
    public function test_impersonation_is_recorded_and_is_not_a_grant(): void
    {
        $admin = $this->user(['name' => 'Admin']);
        $admin->givePermissionTo('users.impersonate');
        $target = $this->user(['name' => 'Member']);

        AuthorizationContext::impersonating($admin);

        $report = $target->authorizeFor('users.impersonate');

        $this->assertTrue($report->denied());
        $this->assertTrue($report->metadata['impersonating']);
        $this->assertStringContainsString((string) $admin->getKey(), $report->metadata['original_user']);
        $this->assertStringContainsString((string) $target->getKey(), $report->metadata['effective_user']);
        $this->assertFalse($target->can('users.impersonate'));
    }

    public function test_flush_clears_impersonation(): void
    {
        AuthorizationContext::impersonating($this->user());
        AuthorizationContext::flush();

        $this->assertFalse(AuthorizationContext::isImpersonating());
        $this->assertNull(AuthorizationContext::impersonator());
    }
}
