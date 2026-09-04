<?php

namespace Libinkk\Permission\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Libinkk\Permission\Authorization\Decision;

class AuthorizationDenied
{
    use Dispatchable;

    public function __construct(public Decision $decision)
    {
    }
}
