<?php

namespace Libinkk\Permission\Audit;

use Illuminate\Database\Eloquent\Model;
use Libinkk\Permission\Support\Tables;
use Libinkk\Permission\Support\UsesConfiguredKeys;

class AuthorizationAudit extends Model
{
    use UsesConfiguredKeys;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return Tables::authorizationAudits();
    }

    /**
     * Audit rows are append-only. Soft deletes are never used.
     */
    public function delete(): bool
    {
        return false;
    }
}
