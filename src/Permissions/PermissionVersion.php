<?php

namespace Libinkk\Permission\Permissions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Libinkk\Permission\Support\Tables;
use Libinkk\Permission\Support\UsesConfiguredKeys;

class PermissionVersion extends Model
{
    use UsesConfiguredKeys;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'definition' => 'array',
        'version' => 'integer',
        'created_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return Tables::permissionVersions();
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }
}
