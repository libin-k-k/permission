<?php

namespace Libinkk\Permission\Exceptions;

use RuntimeException;

class CannotDelegatePermissionException extends RuntimeException
{
    public static function missing(string $permission): self
    {
        return new self("Cannot delegate [{$permission}] because the delegator does not currently have it.");
    }

    public static function disabled(): self
    {
        return new self('Permission delegation is disabled.');
    }

    public static function selfDelegation(): self
    {
        return new self('A user cannot delegate a permission to themselves.');
    }

    public static function cannotRevoke(): self
    {
        return new self('Only the delegator can revoke a delegation.');
    }
}
