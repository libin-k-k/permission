<?php

namespace Libinkk\Permission\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    protected $guarded = [];

    protected $table = 'workspaces';

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
