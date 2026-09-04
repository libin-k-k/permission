<?php

namespace Libinkk\Permission\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Libinkk\Permission\Permissions\Permission;

class PermissionCreated
{
    use Dispatchable;

    public function __construct(public Permission $permission)
    {
    }
}
