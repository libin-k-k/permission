<?php

namespace Libinkk\Permission\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Libinkk\Permission\Concerns\HasAuthorization;

class User extends Authenticatable
{
    use HasAuthorization;

    protected $guarded = [];

    protected $table = 'users';
}
