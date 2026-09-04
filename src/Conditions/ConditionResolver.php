<?php

namespace Libinkk\Permission\Conditions;

use Closure;
use Illuminate\Support\Facades\DB;
use Libinkk\Permission\Contracts\PermissionCache;
use Libinkk\Permission\Exceptions\ConditionEvaluationException;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Support\Tables;
use Throwable;

class ConditionResolver
{
    public function __construct(
        protected ConditionRegistry $registry,
        protected PermissionCache $cache,
    ) {
    }

    /**
     * @return array{passed: bool, results: array<string, bool|string>}
     */
    public function evaluate(object $user, string $permission, mixed $resource = null, array $arguments = []): array
    {
        $results = [];
        $allPassed = true;

        foreach ($this->runtimeConditions($permission) as $condition) {
            $passed = $this->runSafe($condition['name'], function () use ($condition, $user, $resource, $arguments) {
                if ($condition['callback'] instanceof Closure) {
                    return $this->invoke($condition['callback'], $user, $resource, $arguments, $condition['options']);
                }

                return $this->runNamed($condition['name'], $user, $resource, $arguments, $condition['options']);
            });

            $results[$condition['name']] = $passed;
            $allPassed = $allPassed && $passed;
        }

        foreach ($this->databaseConditions($permission) as $condition) {
            $passed = $this->runSafe($condition['name'], function () use ($condition, $user, $resource, $arguments) {
                return $this->evaluateDatabaseCondition($condition, $user, $resource, $arguments);
            });

            $results[$condition['name']] = $passed;
            if ($condition['is_required']) {
                $allPassed = $allPassed && $passed;
            }
        }

        if ($this->requiresOwnership($permission)) {
            $passed = OwnershipChecker::owns(
                $user,
                $resource,
                attribute: config('permission.ownership.attribute')
            );
            $results['owner'] = $passed;
            $allPassed = $allPassed && $passed;
        }

        return [
            'passed' => $allPassed,
            'results' => $results,
        ];
    }

    public function hasConditions(string $permission): bool
    {
        return $this->runtimeConditions($permission) !== []
            || $this->databaseConditions($permission) !== []
            || $this->requiresOwnership($permission);
    }

    protected function requiresOwnership(string $permission): bool
    {
        if (! config('permission.ownership.auto_own_suffix', true)) {
            return false;
        }

        return str_ends_with($permission, '.own');
    }

    /**
     * @return list<array{name: string, callback: Closure|null, options: array<string, mixed>}>
     */
    protected function runtimeConditions(string $permission): array
    {
        return $this->registry->forPermission($permission);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function databaseConditions(string $permission): array
    {
        return $this->cache->remember(
            "permission:{$permission}:db-conditions",
            'permissions',
            fn () => $this->loadDatabaseConditions($permission),
            persistent: false
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function loadDatabaseConditions(string $permission): array
    {
        $model = Permission::query()
            ->where(fn ($query) => $query->where('name', $permission)->orWhere('slug', $permission))
            ->first();

        if (! $model) {
            return [];
        }

        $conditions = Tables::get('permission_conditions', 'permission_conditions');

        return DB::table($conditions)
            ->where('permission_id', $model->getKey())
            ->where('is_active', true)
            ->orderBy('priority')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  array<int, mixed>  $arguments
     */
    protected function evaluateDatabaseCondition(array $condition, object $user, mixed $resource, array $arguments): bool
    {
        $type = (string) ($condition['type'] ?? 'custom');
        $name = (string) $condition['name'];

        return match ($type) {
            'ownership' => OwnershipChecker::owns(
                $user,
                $resource,
                attribute: is_string($condition['value'] ?? null) ? $condition['value'] : null
            ),
            'closure', 'custom', 'attribute', 'role', 'time', 'date', 'tenant', 'policy' => $this->runNamed(
                $name,
                $user,
                $resource,
                $arguments,
                ['operator' => $condition['operator'] ?? null, 'value' => $condition['value'] ?? null]
            ),
            default => $this->runNamed($name, $user, $resource, $arguments, []),
        };
    }

    /**
     * @param  array<int, mixed>  $arguments
     * @param  array<string, mixed>  $options
     */
    protected function runNamed(string $name, object $user, mixed $resource, array $arguments, array $options): bool
    {
        $callback = $this->registry->get($name);

        if (! $callback) {
            throw new ConditionEvaluationException("Condition [{$name}] is not registered.");
        }

        return $this->invoke($callback, $user, $resource, $arguments, $options);
    }

    /**
     * @param  array<int, mixed>  $arguments
     * @param  array<string, mixed>  $options
     */
    protected function invoke(Closure $callback, object $user, mixed $resource, array $arguments, array $options = []): bool
    {
        $params = (new \ReflectionFunction($callback))->getNumberOfParameters();

        return (bool) match (true) {
            $params <= 1 => $callback($user),
            $params === 2 => $callback($user, $resource),
            $params === 3 => $callback($user, $resource, $options),
            default => $callback($user, $resource, $options, ...array_slice($arguments, 1)),
        };
    }

    protected function runSafe(string $name, Closure $callback): bool
    {
        try {
            return (bool) $callback();
        } catch (Throwable) {
            // Fail closed: unknown/broken conditions never grant access.
            return false;
        }
    }
}
