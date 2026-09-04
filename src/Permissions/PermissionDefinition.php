<?php

namespace Libinkk\Permission\Permissions;

use Closure;
use Libinkk\Permission\Conditions\ConditionRegistry;
use Libinkk\Permission\Conditions\PermissionCondition;

class PermissionDefinition
{
    public function __construct(
        protected Permission $permission,
    ) {
    }

    /**
     * Attach a runtime condition (closure or named condition).
     *
     * @param  array<string, mixed>  $options
     */
    public function when(Closure|string $condition, array $options = []): self
    {
        app(ConditionRegistry::class)->attach($this->permission->name, $condition, $options);

        if (is_string($condition) && config('permission.conditions.persist_named', true)) {
            $this->persistNamed($condition, $options);
        }

        return $this;
    }

    public function permission(): Permission
    {
        return $this->permission;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function persistNamed(string $name, array $options): void
    {
        $exists = PermissionCondition::query()
            ->where('permission_id', $this->permission->getKey())
            ->where('name', $name)
            ->exists();

        if ($exists) {
            return;
        }

        PermissionCondition::query()->create([
            'permission_id' => $this->permission->getKey(),
            'name' => $name,
            'type' => $options['type'] ?? ($name === 'owner' ? 'ownership' : 'custom'),
            'operator' => $options['operator'] ?? null,
            'value' => $options['value'] ?? null,
            'priority' => $options['priority'] ?? 0,
            'is_required' => $options['is_required'] ?? true,
            'is_active' => true,
            'metadata' => $options['metadata'] ?? null,
        ]);
    }
}
