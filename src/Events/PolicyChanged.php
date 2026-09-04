<?php

namespace Libinkk\Permission\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Permissions\PermissionVersion;

class PolicyChanged
{
    use Dispatchable;

    public function __construct(
        public Permission $permission,
        public PermissionVersion $version,
    ) {
    }
}
