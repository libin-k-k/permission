<?php

namespace Libinkk\Permission\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Libinkk\Permission\Delegation\Delegation;
use Libinkk\Permission\Permissions\Permission;

class DelegationCreated
{
    use Dispatchable;

    public function __construct(
        public Delegation $delegation,
        public object $from,
        public object $to,
        public Permission $permission,
    ) {
    }
}
