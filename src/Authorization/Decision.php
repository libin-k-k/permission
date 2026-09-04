<?php

namespace Libinkk\Permission\Authorization;

final class Decision
{
    public function __construct(
        public bool $allowed,
        public string $permission,
        public mixed $user = null,
        public ?string $role = null,
        public ?string $scope = null,
        public mixed $resource = null,
        public array $conditions = [],
        public ?string $reason = null,
        public ?string $source = null,
        public array $metadata = [],
        public array $checks = [],
    ) {
    }

    public static function allow(
        string $permission,
        mixed $user = null,
        ?string $role = null,
        mixed $resource = null,
        ?string $source = null,
        array $metadata = [],
        array $checks = [],
    ): self {
        return new self(
            allowed: true,
            permission: $permission,
            user: $user,
            role: $role,
            resource: $resource,
            reason: DecisionReason::ALLOWED,
            source: $source,
            metadata: $metadata,
            checks: $checks,
        );
    }

    public static function deny(
        string $permission,
        mixed $user = null,
        ?string $role = null,
        mixed $resource = null,
        ?string $reason = DecisionReason::PERMISSION_MISSING,
        ?string $source = 'engine',
        array $metadata = [],
        array $checks = [],
    ): self {
        return new self(
            allowed: false,
            permission: $permission,
            user: $user,
            role: $role,
            resource: $resource,
            reason: $reason,
            source: $source,
            metadata: $metadata,
            checks: $checks,
        );
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'permission' => $this->permission,
            'user_id' => is_object($this->user) && method_exists($this->user, 'getKey')
                ? $this->user->getKey()
                : $this->user,
            'role' => $this->role,
            'scope' => $this->scope,
            'resource' => $this->resourceKey($this->resource),
            'conditions' => $this->conditions,
            'reason' => $this->reason,
            'source' => $this->source,
            'checks' => $this->checks,
            'metadata' => $this->metadata,
        ];
    }

    private function resourceKey(mixed $resource): mixed
    {
        if (! is_object($resource)) {
            return $resource;
        }

        $type = class_basename($resource);
        $id = method_exists($resource, 'getKey') ? $resource->getKey() : null;

        return $id !== null ? "{$type}:{$id}" : $type;
    }
}
