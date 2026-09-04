<?php

namespace Libinkk\Permission\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Libinkk\Permission\Roles\Role;

class RoleDeleted
{
    use Dispatchable;

    public function __construct(public Role $role)
    {
    }
}
