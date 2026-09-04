<?php

namespace Libinkk\Permission\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Libinkk\Permission\Permissions\Permission;

class PermissionGranted
{
    use Dispatchable;

    public function __construct(public object $user, public Permission $permission)
    {
    }
}
