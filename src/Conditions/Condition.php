<?php

namespace Libinkk\Permission\Conditions;

use Closure;

/**
 * Facade-style entry for defining named conditions.
 */
class Condition
{
    public static function define(string $name, Closure $callback): void
    {
        app(ConditionRegistry::class)->define($name, $callback);
    }

    public static function registry(): ConditionRegistry
    {
        return app(ConditionRegistry::class);
    }
}
