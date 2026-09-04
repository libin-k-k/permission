<?php

namespace Libinkk\Permission\Conditions;

use Closure;

class ConditionRegistry
{
    /**
     * Named global conditions.
     *
     * @var array<string, Closure>
     */
    protected array $named = [];

    /**
     * Runtime conditions attached to permission names.
     *
     * @var array<string, list<array{name: string, callback: Closure|null, options: array<string, mixed>}>>
     */
    protected array $forPermission = [];

    public function define(string $name, Closure $callback): void
    {
        $this->named[$name] = $callback;
    }

    public function has(string $name): bool
    {
        return isset($this->named[$name]);
    }

    public function get(string $name): ?Closure
    {
        return $this->named[$name] ?? null;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function attach(string $permission, Closure|string $condition, array $options = []): void
    {
        $name = is_string($condition) ? $condition : ($options['name'] ?? 'closure_'.count($this->forPermission[$permission] ?? []));
        $callback = $condition instanceof Closure ? $condition : null;

        $this->forPermission[$permission][] = [
            'name' => $name,
            'callback' => $callback,
            'options' => $options,
        ];
    }

    /**
     * @return list<array{name: string, callback: Closure|null, options: array<string, mixed>}>
     */
    public function forPermission(string $permission): array
    {
        return $this->forPermission[$permission] ?? [];
    }

    public function flush(): void
    {
        $this->forPermission = [];
    }

    public function flushNamed(): void
    {
        $this->named = [];
    }
}
