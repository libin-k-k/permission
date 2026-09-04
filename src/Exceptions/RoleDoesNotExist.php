<?php

namespace Libinkk\Permission\Exceptions;

use RuntimeException;

class RoleDoesNotExist extends RuntimeException
{
    public static function named(string $name, string $guard): self
    {
        return new self("Role [{$name}] does not exist for guard [{$guard}].");
    }
}
