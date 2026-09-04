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

    public function forceDelete(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        return false;
    }
}
