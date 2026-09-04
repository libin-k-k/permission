<?php

namespace Libinkk\Permission\Tests\Unit;

use Libinkk\Permission\Authorization\DecisionReason;
use Libinkk\Permission\Delegation\Delegation;
use Libinkk\Permission\Exceptions\CannotDelegatePermissionException;
use Libinkk\Permission\Tests\Fixtures\Organization;
use Libinkk\Permission\Tests\TestCase;

class DelegationTest extends TestCase
{
    public function test_delegatee_can_use_active_delegation(): void
    {
        $owner = $this->user();
        $manager = $this->user();
        $owner->givePermissionTo('invoice.approve');

        $delegation = $owner->delegate(
            'invoice.approve',
            to: $manager,
            until: now()->addHours(4),
            reason: 'On leave'
        );

        $this->assertSame(Delegation::STATUS_ACTIVE, $delegation->status);

        $decision = $this->assertCan($manager, 'invoice.approve');
        $this->assertSame('delegation', $decision->source);
        $this->assertSame($delegation->getKey(), $decision->metadata['delegation_id']);
        $this->assertFalse($owner->can('posts.create'));
    }

    public function test_cannot_delegate_permission_the_user_does_not_have(): void
    {
        $this->expectException(CannotDelegatePermissionException::class);

        $this->user()->delegate('invoice.approve', to: $this->user(), until: now()->addHour());
    }

    public function test_expired_delegation_never_authorizes(): void
    {
        $owner = $this->user();
        $manager = $this->user();
        $owner->givePermissionTo('invoice.approve');

        $owner->delegate('invoice.approve', to: $manager, until: now()->subMinute());

        $decision = $this->assertCannot($manager, 'invoice.approve');
        $this->assertSame(DecisionReason::DELEGATION_EXPIRED, $decision->reason);
    }

    public function test_revoked_delegation_never_authorizes(): void
    {
        $owner = $this->user();
        $manager = $this->user();
        $owner->givePermissionTo('invoice.approve');

        $delegation = $owner->delegate('invoice.approve', to: $manager, until: now()->addHours(4));
        $owner->revokeDelegation($delegation);

        $this->assertCannot($manager, 'invoice.approve');
        $this->assertSame(Delegation::STATUS_REVOKED, $delegation->fresh()->status);
    }

    public function test_pending_delegation_does_not_authorize(): void
    {
        $owner = $this->user();
        $manager = $this->user();
        $owner->givePermissionTo('invoice.approve');

        $delegation = $owner->delegate(
            'invoice.approve',
            to: $manager,
            startsAt: now()->addHour(),
            until: now()->addHours(5)
        );

        $this->assertSame(Delegation::STATUS_PENDING, $delegation->status);
        $this->assertCannot($manager, 'invoice.approve');
    }

    public function test_lost_delegator_access_revokes_effective_delegation(): void
    {
        $owner = $this->user();
        $manager = $this->user();
        $owner->givePermissionTo('invoice.approve');
        $owner->delegate('invoice.approve', to: $manager, until: now()->addHours(4));

        $this->assertCan($manager, 'invoice.approve');

        $owner->revokePermissionTo('invoice.approve');

        $this->assertCannot($manager, 'invoice.approve');
    }

    public function test_resource_specific_delegation(): void
    {
        $owner = $this->user();
        $manager = $this->user();
        $invoice = Organization::query()->create(['name' => 'Invoice A']);
        $other = Organization::query()->create(['name' => 'Invoice B']);

        $owner->givePermissionTo('invoice.approve');
        $owner->delegate(
            'invoice.approve',
            to: $manager,
            until: now()->addHours(4),
            resource: $invoice
        );

        $this->assertCan($manager, 'invoice.approve', $invoice);
        $this->assertCannot($manager, 'invoice.approve', $other);
        $this->assertCannot($manager, 'invoice.approve');
    }

    public function test_explicit_deny_beats_delegation(): void
    {
        $owner = $this->user();
        $manager = $this->user();
        $owner->givePermissionTo('invoice.approve');
        $owner->delegate('invoice.approve', to: $manager, until: now()->addHours(4));
        $manager->denyPermissionTo('invoice.approve');

        $decision = $this->assertCannot($manager, 'invoice.approve');
        $this->assertSame(DecisionReason::EXPLICIT_DENY, $decision->reason);
    }

    public function test_export_includes_delegations(): void
    {
        $owner = $this->user();
        $manager = $this->user();
        $owner->givePermissionTo('invoice.approve');
        $owner->delegate('invoice.approve', to: $manager, until: now()->addHours(4));

        $export = $manager->exportAccess();
        $this->assertCount(1, $export['delegations']['received']);
        $this->assertSame('invoice.approve', $export['delegations']['received'][0]['permission']);
        $this->assertSame(1, $export['totals']['delegations']);
    }
}
