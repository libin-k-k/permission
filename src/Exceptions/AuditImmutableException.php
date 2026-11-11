<?php

namespace Libinkk\Permission\Exceptions;

use RuntimeException;

class AuditImmutableException extends RuntimeException
{
    public static function cannotModify(): self
    {
        return new self('authorization_audits rows are append-only and cannot be updated or deleted.');
    }
}
