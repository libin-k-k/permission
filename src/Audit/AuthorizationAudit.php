<?php

namespace Libinkk\Permission\Audit;

use Illuminate\Database\Eloquent\Model;
use Libinkk\Permission\Exceptions\AuditImmutableException;
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

    protected static function booted(): void
    {
        static::updating(function () {
            throw AuditImmutableException::cannotModify();
        });

        static::deleting(function () {
            throw AuditImmutableException::cannotModify();
        });
    }

    public function getTable(): string
    {
        return Tables::authorizationAudits();
    }

    /**
     * Audit rows are append-only. Soft deletes are never used.
     */
    public function delete(): bool
    {
        throw AuditImmutableException::cannotModify();
    }

    public function forceDelete(): bool
    {
        throw AuditImmutableException::cannotModify();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw AuditImmutableException::cannotModify();
    }
}
