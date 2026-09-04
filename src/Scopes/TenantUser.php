<?php

namespace Libinkk\Permission\Scopes;

use Illuminate\Database\Eloquent\Model;
use Libinkk\Permission\Support\Tables;
use Libinkk\Permission\Support\UsesConfiguredKeys;

class TenantUser extends Model
{
    use UsesConfiguredKeys;

    protected $guarded = [];

    public function getTable(): string
    {
        return Tables::get('tenant_users', 'tenant_users');
    }
}
