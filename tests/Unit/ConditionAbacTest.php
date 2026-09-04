<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Authorization\DecisionReason;
use Libinkk\Permission\Conditions\Condition;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Tests\Fixtures\User;
use Libinkk\Permission\Tests\TestCase;

class ConditionAbacTest extends TestCase
{
    public function test_closure_condition_must_pass(): void
    {
        Permission::define('invoice.approve')
            ->when(fn ($user, $invoice) => ($invoice->amount ?? 0) <= ($user->approval_limit ?? 0));

        $user = $this->user(['approval_limit' => 1000]);
        $user->givePermissionTo('invoice.approve');

        $ok = (object) ['amount' => 500];
        $tooHigh = (object) ['amount' => 5000];

        $this->assertCan($user, 'invoice.approve', $ok);

        $decision = $this->assertCannot($user, 'invoice.approve', $tooHigh);
        $this->assertSame(DecisionReason::CONDITION_FAILED, $decision->reason);
        $this->assertNotEmpty($decision->conditions);
    }

    public function test_named_condition(): void
    {
        Condition::define('within_approval_limit', function ($user, $invoice) {
            return ($invoice->amount ?? 0) <= ($user->approval_limit ?? 0);
        });

        Permission::define('invoice.approve')->when('within_approval_limit');

        $user = $this->user(['approval_limit' => 100]);
        $user->givePermissionTo('invoice.approve');

        $this->assertCan($user, 'invoice.approve', (object) ['amount' => 50]);
        $this->assertCannot($user, 'invoice.approve', (object) ['amount' => 200]);
    }

    public function test_ownership_suffix_and_owner_condition(): void
    {
        Permission::define('posts.update.own');
        $owner = $this->user();
        $other = $this->user();
        $owner->givePermissionTo('posts.update.own');

        $post = (object) ['author_id' => $owner->getKey()];

        $this->assertCan($owner, 'posts.update.own', $post);

        $decision = $this->assertCannot($other, 'posts.update.own', $post);
        $this->assertTrue(in_array($decision->reason, [
            DecisionReason::RESOURCE_DENIED,
            DecisionReason::PERMISSION_MISSING,
            DecisionReason::CONDITION_FAILED,
        ], true));

        // other has permission but not ownership
        $other->givePermissionTo('posts.update.own');
        $denied = $this->assertCannot($other, 'posts.update.own', $post);
        $this->assertSame(DecisionReason::RESOURCE_DENIED, $denied->reason);
    }

    public function test_explain_includes_condition_checks(): void
    {
        Permission::define('reports.export')
            ->when(fn () => true);

        $user = $this->user();
        $user->givePermissionTo('reports.export');

        $explain = $user->explain('reports.export');

        $this->assertTrue($explain['allowed']);
        $this->assertSame(DecisionReason::ALLOWED, $explain['reason']);
        $this->assertArrayHasKey('checks', $explain);
    }
}
