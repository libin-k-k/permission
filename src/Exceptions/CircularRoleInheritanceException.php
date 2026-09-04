<?php

namespace Libinkk\Permission\Exceptions;

use RuntimeException;

class CircularRoleInheritanceException extends RuntimeException
{
    public static function for(string $parent, string $child): self
    {
        return new self("Circular role inheritance detected: [{$parent}] → [{$child}].");
    }
}
