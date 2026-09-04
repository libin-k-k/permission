<?php

namespace Libinkk\Permission\Support;

use Illuminate\Support\Str;

trait UsesConfiguredKeys
{
    public function initializeUsesConfiguredKeys(): void
    {
        $type = config('permission.database.primary_key', 'bigint');

        $this->incrementing = $type === 'bigint';
        $this->keyType = $type === 'bigint' ? 'int' : 'string';
    }

    protected static function bootUsesConfiguredKeys(): void
    {
        static::creating(function ($model) {
            if ($model->getKey()) {
                return;
            }

            $type = config('permission.database.primary_key', 'bigint');

            if ($type === 'uuid') {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }

            if ($type === 'ulid') {
                $model->{$model->getKeyName()} = (string) Str::ulid();
            }
        });
    }
}
