<?php

namespace Libinkk\Permission\Conditions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Support\Tables;
use Libinkk\Permission\Support\UsesConfiguredKeys;

class PermissionCondition extends Model
{
    use UsesConfiguredKeys;

    protected $guarded = [];

    protected $casts = [
        'priority' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return Tables::get('permission_conditions', 'permission_conditions');
    }

    public function permission(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(PermissionConditionValue::class, 'condition_id');
    }
}
