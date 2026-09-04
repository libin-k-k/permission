<?php

namespace Libinkk\Permission\Exceptions;

use RuntimeException;

class SystemRecordProtectedException extends RuntimeException
{
    public static function cannotDelete(string $type, string $name): self
    {
        return new self("System {$type} [{$name}] cannot be deleted.");
    }

    public static function cannotMutate(string $type, string $name): self
    {
        return new self("System {$type} [{$name}] cannot be stripped of system protection.");
    }
}
