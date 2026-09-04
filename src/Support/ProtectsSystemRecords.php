<?php

namespace Libinkk\Permission\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Libinkk\Permission\Exceptions\SystemRecordProtectedException;

trait ProtectsSystemRecords
{
    /**
     * @param  Closure(static): mixed  $callback
     */
    abstract public static function updating($callback);

    /**
     * @param  Closure(static): mixed  $callback
     */
    abstract public static function deleting($callback);

    protected static function bootProtectsSystemRecords(): void
    {
        static::updating(function (Model $model) {
            if (! $model->getOriginal('is_system')) {
                return;
            }

            if ($model->isDirty('is_system') && ! $model->getAttribute('is_system')) {
                throw SystemRecordProtectedException::cannotMutate(
                    strtolower(class_basename($model)),
                    (string) ($model->getOriginal('name') ?: $model->getOriginal('slug'))
                );
            }
        });

        static::deleting(function (Model $model) {
            if ($model->getAttribute('is_system')) {
                throw SystemRecordProtectedException::cannotDelete(
                    strtolower(class_basename($model)),
                    (string) ($model->getAttribute('name') ?: $model->getAttribute('slug'))
                );
            }
        });
    }
}
