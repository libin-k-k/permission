<?php

namespace Libinkk\Permission\Scopes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Libinkk\Permission\Support\Tables;
use Libinkk\Permission\Support\UsesConfiguredKeys;

class Tenant extends Model
{
    use UsesConfiguredKeys;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $tenant) {
            $tenant->slug ??= Str::slug((string) $tenant->name);
        });
    }

    public function getTable(): string
    {
        return Tables::get('tenants', 'tenants');
    }

    public function users(): HasMany
    {
        return $this->hasMany(TenantUser::class, 'tenant_id');
    }
}
