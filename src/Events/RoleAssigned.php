<?php

namespace Libinkk\Permission\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Libinkk\Permission\Roles\Role;

class RoleAssigned
{
    use Dispatchable;

    public function __construct(public object $user, public Role $role)
    {
    }
}
