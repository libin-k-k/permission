<?php

namespace Libinkk\Permission\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE)]
class Permission
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $group = null,
        public ?string $resource = null,
        public ?string $action = null,
        public ?string $guard = null,
        public ?string $risk_level = null,
        public bool $is_dangerous = false,
        public bool $requires_audit = false,
    ) {
    }
}
