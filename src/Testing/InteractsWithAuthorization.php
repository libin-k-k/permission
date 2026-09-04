<?php

namespace Libinkk\Permission\Testing;

use Libinkk\Permission\Authorization\Decision;

trait InteractsWithAuthorization
{
    public function assertCan(object $user, string $permission, mixed $resource = null): Decision
    {
        $decision = $user->authorizeFor($permission, $resource);

        $this->assertTrue(
            $decision->allowed(),
            sprintf('Failed asserting that the user can [%s]. Reason: %s', $permission, $decision->reason)
        );

        return $decision;
    }

    public function assertCannot(object $user, string $permission, mixed $resource = null): Decision
    {
        $decision = $user->authorizeFor($permission, $resource);

        $this->assertFalse(
            $decision->allowed(),
            sprintf('Failed asserting that the user cannot [%s]. Source: %s', $permission, $decision->source)
        );

        return $decision;
    }
}
