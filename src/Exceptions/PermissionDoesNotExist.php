<?php

namespace Libinkk\Permission\Exceptions;

use RuntimeException;

class PermissionDoesNotExist extends RuntimeException
{
    public static function named(string $name, string $guard): self
    {
        return new self("Permission [{$name}] does not exist for guard [{$guard}].");
    }
}
