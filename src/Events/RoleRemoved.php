<?php

namespace Libinkk\Permission\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Libinkk\Permission\Roles\Role;

class RoleRemoved
{
    use Dispatchable;

    public function __construct(public object $user, public Role $role)
    {
    }
}
