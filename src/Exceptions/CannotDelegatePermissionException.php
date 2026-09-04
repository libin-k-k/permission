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
}
