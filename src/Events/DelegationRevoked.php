<?php

namespace Libinkk\Permission\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Libinkk\Permission\Delegation\Delegation;

class DelegationRevoked
{
    use Dispatchable;

    public function __construct(public Delegation $delegation)
    {
    }
}
