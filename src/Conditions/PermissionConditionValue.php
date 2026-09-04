<?php

namespace Libinkk\Permission\Conditions;

use Illuminate\Database\Eloquent\Model;
use Libinkk\Permission\Support\Tables;
use Libinkk\Permission\Support\UsesConfiguredKeys;

class PermissionConditionValue extends Model
{
    use UsesConfiguredKeys;

    protected $guarded = [];

    public function getTable(): string
    {
        return Tables::get('permission_condition_values', 'permission_condition_values');
    }

    public function condition(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PermissionCondition::class, 'condition_id');
    }
}
